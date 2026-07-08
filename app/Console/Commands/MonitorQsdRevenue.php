<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class MonitorQsdRevenue extends Command
{
    protected $signature = 'qsd:monitor-revenue';
    protected $description = 'Monitor pergerakan nilai daily_qsd setiap 5 menit (dengan Tracing & SUM)';

    public function handle()
    {
        $batchId = uniqid('MONITOR-REV-');
        
        $now = Carbon::now();

        // Batas bulan berjalan dipakai SEBAGAI SATU SUMBER KEBENARAN (single source of truth)
        // untuk kedua query di bawah, supaya tidak ada celah tanggal yang beda antara
        // pengambilan log terakhir vs pengambilan data live dari daily_qsd.
        $startOfMonth = $now->copy()->startOfMonth()->startOfDay();
        $endOfMonth   = $now->copy()->endOfMonth()->endOfDay();

        $this->info("[$batchId] Memulai monitoring pergerakan data revenue...");
        Log::channel('monitor_log_qsd_revenue')->info("[$batchId] Memulai monitoring", ['waktu' => $now->toDateTimeString()]);

        try {
            // ================================================================
            // STEP 1: Ambil SALDO BERJALAN (Grand Total Terakhir) di bulan ini
            // Ini akan menjadi titik awal penambahan/pengurangan saat script jalan
            // ================================================================
            $currentGrandTotal = DB::table('qsd_revenue_transaction_log')
                ->whereBetween('tanggal_kelompok', [$startOfMonth, $endOfMonth])
                ->orderByDesc('id') // Ambil transaksi paling terakhir
                ->value('total');

            $currentGrandTotal = $currentGrandTotal ? (float) $currentGrandTotal : 0.0;

            // ================================================================
            // STEP 2: Ambil riwayat NILAI PER ORDER di bulan ini
            // Karena kolom 'total' sekarang berisi Grand Total, kita hitung 
            // nilai terakhir tiap order dengan menjumlahkan riwayat revenue-nya
            // ================================================================
            $latestLogs = DB::table('qsd_revenue_transaction_log')
                ->select(
                    'no_order', 
                    DB::raw('MAX(periode) as periode'), 
                    DB::raw('MAX(tanggal_kelompok) as tanggal_kelompok'),
                    DB::raw('SUM(CASE WHEN status = "penambahan" THEN revenue ELSE -revenue END) as order_total')
                )
                ->whereBetween('tanggal_kelompok', [$startOfMonth, $endOfMonth])
                ->groupBy('no_order')
                ->get()
                ->keyBy('no_order');

            $countInsert = 0;
            $processedOrders = []; 
            $insertData = [];

            // ================================================================
            // STEP 3: Cek dari daily_qsd ke Log (Update Saldo Berjalan)
            // ================================================================
            DB::table('daily_qsd')
                ->select(
                    'no_order', 
                    DB::raw('MAX(periode) as periode'), 
                    DB::raw('MAX(tanggal_kelompok) as tanggal_kelompok'), 
                    DB::raw('SUM(total_revenue) as total_revenue')
                )
                ->whereBetween('tanggal_kelompok', [$startOfMonth, $endOfMonth])
                ->groupBy('no_order')
                ->orderBy('no_order')
                ->chunk(500, function ($records) use ($latestLogs, $now, &$insertData, &$processedOrders, &$currentGrandTotal) {
                    
                    foreach ($records as $row) {
                        $orderId = $row->no_order;
                        if (empty($orderId)) continue; 

                        $processedOrders[] = $orderId; 

                        $currentValue = round((float) $row->total_revenue, 2);
                        $lastOrderTotal = 0;
                        $isNewOrder = true;

                        if ($latestLogs->has($orderId)) {
                            $lastOrderTotal = round((float) $latestLogs->get($orderId)->order_total, 2);
                            $isNewOrder = false;
                        }

                        if (!$isNewOrder && abs($currentValue - $lastOrderTotal) < 0.01) {
                            continue; 
                        }

                        $isRevisi = !$isNewOrder; 

                        if ($isNewOrder || $currentValue > $lastOrderTotal) {
                            $status = 'penambahan';
                            $revenueDiff = round($currentValue - $lastOrderTotal, 2);
                            
                            // UPDATE SALDO BERJALAN MENGGUNAKAN SELISIH
                            $currentGrandTotal += $revenueDiff;
                        } else {
                            $status = 'pengurangan';
                            $revenueDiff = round($lastOrderTotal - $currentValue, 2);
                            
                            // UPDATE SALDO BERJALAN MENGGUNAKAN SELISIH
                            $currentGrandTotal -= $revenueDiff;
                        }

                        $insertData[] = [
                            'no_order'         => $orderId,
                            'periode'          => $row->periode,
                            'tanggal_kelompok' => $row->tanggal_kelompok,
                            'revenue'          => $revenueDiff,
                            'status'           => $status,
                            'total'            => $currentGrandTotal, // <-- Simpan Saldo Berjalan
                            'is_revisi'        => $isRevisi,
                            'created_at'       => $now,
                        ];
                    }
                });

            // ================================================================
            // STEP 4: Menangkap Order yang di-HARD REMOVE
            // ================================================================
            $deletedOrders = $latestLogs->except($processedOrders);

            foreach ($deletedOrders as $orderId => $logData) {
                $lastOrderTotal = round((float) $logData->order_total, 2);

                if ($lastOrderTotal > 0) {
                    
                    // ORDER DIHAPUS -> KURANGI SALDO BERJALAN
                    $currentGrandTotal -= $lastOrderTotal;

                    $insertData[] = [
                        'no_order'         => $orderId,
                        'periode'          => $logData->periode,
                        'tanggal_kelompok' => $logData->tanggal_kelompok,
                        'revenue'          => $lastOrderTotal, 
                        'status'           => 'pengurangan',
                        'total'            => $currentGrandTotal, // <-- Simpan Saldo Berjalan
                        'is_revisi'        => true,
                        'created_at'       => $now,
                    ];
                }
            }

            // ================================================================
            // STEP 5: Batch Insert ke Database
            // ================================================================
            if (!empty($insertData)) {
                $chunks = array_chunk($insertData, 500);
                foreach ($chunks as $chunk) {
                    DB::table('qsd_revenue_transaction_log')->insert($chunk);
                    $countInsert += count($chunk);
                }
            }

            $this->info("[$batchId] Selesai! Terdeteksi {$countInsert} pergerakan.");
            Log::channel('monitor_log_qsd_revenue')->info("[$batchId] Selesai", ['pergerakan' => $countInsert]);

        } catch (\Throwable $e) {
            $this->error("[$batchId] Kesalahan fatal sistem.");
            $this->error("Message: " . $e->getMessage()."File: " . $e->getFile() . " (Baris: " . $e->getLine() . ")");
            Log::channel('monitor_log_qsd_revenue')->error("[$batchId] FATAL ERROR", [
                'pesan' => $e->getMessage(),
                'baris' => $e->getLine()
            ]);
        }
    }
}