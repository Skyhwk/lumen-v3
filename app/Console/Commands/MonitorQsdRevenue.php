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
            // 1. Ambil baris LOG TERAKHIR per no_order untuk bulan berjalan.
            // Tambahkan select 'periode' dan 'tanggal_kelompok' agar jika terhapus, kita punya jejak tanggalnya
            $latestLogs = DB::table('qsd_revenue_transaction_log as t1')
                ->select('t1.no_order', 't1.total', 't1.periode', 't1.tanggal_kelompok')
                ->whereBetween('t1.tanggal_kelompok', [$startOfMonth, $endOfMonth])
                ->whereRaw('t1.id = (
                        SELECT MAX(t2.id)
                        FROM qsd_revenue_transaction_log t2
                        WHERE t2.no_order = t1.no_order
                        AND t2.tanggal_kelompok BETWEEN ? AND ?
                    )', [$startOfMonth, $endOfMonth])
                ->get()
                ->keyBy('no_order');

            $countInsert = 0;
            
            // Array untuk menyimpan order apa saja yang MASIH ADA di daily_qsd
            $processedOrders = []; 
            // Array untuk menampung data yang akan di-insert ke log
            $insertData = [];

            // 2. STEP SATU: Cek dari daily_qsd ke Log (Menangkap Order Baru & Perubahan Nilai)
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
                ->chunk(500, function ($records) use ($latestLogs, $now, &$insertData, &$processedOrders) {
                    
                    foreach ($records as $row) {
                        $orderId = $row->no_order;
                        if (empty($orderId)) continue; 

                        // Catat bahwa order ini MASIH ADA di daily_qsd
                        $processedOrders[] = $orderId; 

                        $currentValue = round((float) $row->total_revenue, 2);
                        $lastTotal = 0;
                        $isNewOrder = true;

                        if ($latestLogs->has($orderId)) {
                            $lastTotal = round((float) $latestLogs->get($orderId)->total, 2);
                            $isNewOrder = false;
                        }

                        // Jika nilai tidak berubah sama sekali, lewati (tidak perlu masuk log)
                        if (!$isNewOrder && abs($currentValue - $lastTotal) < 0.01) {
                            continue; 
                        }

                        // LOGIC REVISI: 
                        // Jika bukan order baru (sudah ada di log sebelumnya) tapi nilainya beda = REVISI
                        $isRevisi = !$isNewOrder; 

                        if ($isNewOrder) {
                            $status = 'penambahan';
                            $revenueDiff = $currentValue;
                        } elseif ($currentValue > $lastTotal) {
                            $status = 'penambahan';
                            $revenueDiff = round($currentValue - $lastTotal, 2);
                        } else {
                            $status = 'pengurangan';
                            $revenueDiff = round($lastTotal - $currentValue, 2);
                        }

                        $insertData[] = [
                            'no_order'         => $orderId,
                            'periode'          => $row->periode,
                            'tanggal_kelompok' => $row->tanggal_kelompok,
                            'revenue'          => $revenueDiff,
                            'status'           => $status,
                            'total'            => $currentValue,
                            'is_revisi'        => $isRevisi, // <--- Flag Boolean Revisi
                            'created_at'       => $now,
                        ];
                    }
                });

            // 3. STEP DUA: Cek dari Log ke daily_qsd (Menangkap Order yang di-HARD REMOVE)
            // Cari order yang ada di Log ($latestLogs) tapi TIDAK ADA di array $processedOrders
            $deletedOrders = $latestLogs->except($processedOrders);

            foreach ($deletedOrders as $orderId => $logData) {
                $lastTotal = round((float) $logData->total, 2);

                // Jika last total > 0, berarti dia sebelumnya punya nilai, tapi sekarang hilang
                if ($lastTotal > 0) {
                    $insertData[] = [
                        'no_order'         => $orderId,
                        'periode'          => $logData->periode, // Pakai data lama dari log
                        'tanggal_kelompok' => $logData->tanggal_kelompok, // Pakai data lama dari log
                        'revenue'          => $lastTotal, // Kurangi sebesar total sebelumnya
                        'status'           => 'pengurangan',
                        'total'            => 0, // Total saat ini menjadi 0 (karena dihapus)
                        'is_revisi'        => true, // <--- Menghapus secara teknis adalah revisi ekstrim
                        'created_at'       => $now,
                    ];
                }
            }

            // 4. STEP TIGA: Lakukan Batch Insert ke Database
            if (!empty($insertData)) {
                // Kita pecah array insert menjadi chunk (misal 500 per query)
                // Agar tidak terjadi error 'too many placeholders' dari database jika revisi masif
                $chunks = array_chunk($insertData, 500);
                foreach ($chunks as $chunk) {
                    DB::table('qsd_revenue_transaction_log')->insert($chunk);
                    $countInsert += count($chunk);
                }
            }

            $this->info("[$batchId] Selesai! Terdeteksi {$countInsert} pergerakan.");
            Log::channel('monitor_log_qsd_revenue')->info("[$batchId] Selesai", ['pergerakan' => $countInsert]);

        } catch (Throwable $e) {
            $this->error("[$batchId] Kesalahan fatal sistem.");
            Log::channel('monitor_log_qsd_revenue')->critical("[$batchId] FATAL ERROR", [
                'pesan' => $e->getMessage(),
                'baris' => $e->getLine()
            ]);
        }
    }
}