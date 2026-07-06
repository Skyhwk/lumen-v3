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
            // Ambil baris LOG TERAKHIR per no_order, TAPI HANYA UNTUK BULAN BERJALAN.
            // Dipakai correlated subquery eksplisit (t1.id = MAX(t2.id) dengan filter tanggal
            // yang SAMA PERSIS dengan batas di atas) supaya "ada/belum ada di log bulan ini"
            // dicek dengan rentang tanggal yang identik dengan query daily_qsd di bawah.
            $latestLogs = DB::table('qsd_revenue_transaction_log as t1')
                ->select('t1.no_order', 't1.total')
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

            // GROUP BY hanya no_order agar 1 order = 1 baris (tidak terpecah karena beda periode)
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
                ->chunk(500, function ($records) use ($latestLogs, $now, &$countInsert, $batchId) {
                    
                    try {
                        $insertData = [];

                        foreach ($records as $row) {
                            $orderId = $row->no_order;
                            
                            if (empty($orderId)) continue; 

                            $currentValue = round((float) $row->total_revenue, 2);
                            
                            $lastTotal = 0;
                            $isNewOrder = true;

                            if ($latestLogs->has($orderId)) {
                                $lastTotal = round((float) $latestLogs->get($orderId)->total, 2);
                                $isNewOrder = false;
                            }

                            // DEBUG: Tampilkan perbandingan untuk setiap order
                            $this->info("  [{$orderId}] isNew={$isNewOrder} | DB_SUM={$currentValue} | LOG_TOTAL={$lastTotal} | diff=" . abs($currentValue - $lastTotal));

                            // LOGIC UTAMA: bandingkan dengan toleransi kecil (bukan 10) karena
                            // nilai revenue di sini selalu bulat (Rupiah, tanpa desimal).
                            // Toleransi 10 sebelumnya terlalu longgar dan bisa menyembunyikan
                            // perubahan nilai kecil yang seharusnya tetap di-insert sebagai
                            // penambahan/pengurangan, sehingga log jadi tidak sinkron dengan
                            // total_revenue riil di daily_qsd.
                            if (!$isNewOrder && abs($currentValue - $lastTotal) < 0.01) {
                                $this->info("    → SKIP (nilai sama/dalam toleransi)");
                                continue; 
                            }

                            if ($isNewOrder) {
                                $status = 'penambahan';
                                $revenueDiff = $currentValue;
                                $this->info("    → INSERT (order baru)");
                            } elseif ($currentValue > $lastTotal) {
                                $status = 'penambahan';
                                $revenueDiff = round($currentValue - $lastTotal, 2);
                                $this->info("    → INSERT (penambahan, diff={$revenueDiff})");
                            } else {
                                $status = 'pengurangan';
                                $revenueDiff = round($lastTotal - $currentValue, 2);
                                $this->info("    → INSERT (pengurangan, diff={$revenueDiff})");
                            }

                            $insertData[] = [
                                'no_order'         => $orderId,
                                'periode'          => $row->periode,
                                'tanggal_kelompok' => $row->tanggal_kelompok,
                                'revenue'          => $revenueDiff,
                                'status'           => $status,
                                'total'            => $currentValue,
                                'created_at'       => $now,
                            ];

                            // Update memori log
                            $latestLogs->put($orderId, (object)['total' => $currentValue]);
                        }

                        if (!empty($insertData)) {
                            DB::table('qsd_revenue_transaction_log')->insert($insertData);
                            $countInsert += count($insertData);
                        }

                    } catch (Throwable $e) {
                        Log::channel('monitor_log_qsd_revenue')->error("[$batchId] Gagal memproses chunk", [
                            'pesan' => $e->getMessage(),
                            'baris' => $e->getLine()
                        ]);
                    }
                });

            $this->info("[$batchId] Selesai! Terdeteksi {$countInsert} pergerakan.");
            Log::channel('monitor_log_qsd_revenue')->info("[$batchId] Selesai", ['pergerakan' => $countInsert]);

        } catch (Throwable $e) {
            $this->error("[$batchId] Kesalahan fatal sistem.");
            Log::channel('monitor_log_qsd_revenue')->critical("[$batchId] FATAL ERROR", ['pesan' => $e->getMessage()]);
        }
    }
}