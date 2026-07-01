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
        $currentMonth = $now->format('m');
        $currentYear = $now->format('Y');

        $this->info("[$batchId] Memulai monitoring pergerakan data revenue...");
        Log::channel('monitor_log_qsd_revenue')->info("[$batchId] Memulai monitoring", ['waktu' => $now->toDateTimeString()]);

        try {
            // Ambil referensi data terakhir, TAPI HANYA UNTUK BULAN BERJALAN
            $latestLogs = DB::table('qsd_revenue_transaction_log')
                ->whereIn('id', function($query) use ($currentMonth, $currentYear) {
                    $query->select(DB::raw('MAX(id)'))
                          ->from('qsd_revenue_transaction_log')
                          // Kunci perbaikannya ada di 2 baris ini:
                          ->whereMonth('tanggal_kelompok', $currentMonth)
                          ->whereYear('tanggal_kelompok', $currentYear)
                          ->groupBy('no_order');
                })
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
                ->whereMonth('tanggal_kelompok', $currentMonth)
                ->whereYear('tanggal_kelompok', $currentYear)
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

                            // LOGIC UTAMA: Gunakan TOLERANSI >= 10 karena kolom 'total' bertipe float
                            // MySQL float hanya presisi ~7 digit, angka besar bisa meleset ±5
                            if (!$isNewOrder && abs($currentValue - $lastTotal) < 10) {
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