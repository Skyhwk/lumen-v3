<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class MonitorQsdForecast extends Command
{
    protected $signature = 'qsd:monitor-forecast';
    protected $description = 'Monitor pergerakan nilai forecast_sp setiap 5 menit (dengan Tracing)';

    public function handle()
    {
        // 1. Buat Batch ID unik untuk tracing
        $batchId = uniqid('MONITOR-FC-');
        
        $now = Carbon::now();
        $currentMonth = $now->format('m');
        $currentYear = $now->format('Y');

        $this->info("[$batchId] Memulai monitoring pergerakan data forecast...");
        
        // Pastikan Anda sudah membuat channel 'monitor_log_qsd_forecast' di config logging Anda
        Log::channel('monitor_log_qsd_forecast')->info("[$batchId] Memulai monitoring qsd_forecast", ['waktu' => $now->toDateTimeString()]);

        try {
            // 2. Ambil data terakhir per no_penawaran dari log untuk membandingkan
            $latestLogs = DB::table('qsd_forecast_transaction_log')
                ->whereIn('id', function($query) use ($currentMonth, $currentYear) {
                    $query->select(DB::raw('MAX(id)'))
                          ->from('qsd_forecast_transaction_log')
                           ->whereMonth('tanggal_sampling_min', $currentMonth)
                          ->whereYear('tanggal_sampling_min', $currentYear)
                          ->groupBy('no_penawaran');
                })
                ->get()
                ->keyBy('no_penawaran');

            $countInsert = 0;

            // 3. Tarik data dari forecast_sp berdasarkan tanggal_sampling_min bulan berjalan
            DB::table('forecast_sp')
                ->whereNotNull('tanggal_sampling_min')
                ->whereMonth('tanggal_sampling_min', $currentMonth)
                ->whereYear('tanggal_sampling_min', $currentYear)
                ->orderBy('no_quotation')
                ->chunk(500, function ($records) use ($latestLogs, $now, &$countInsert, $batchId) {
                    
                    // Try-Catch Level Chunk
                    try {
                        $insertData = [];

                        foreach ($records as $row) {
                            $penawaranId = $row->no_quotation;
                            
                            if (empty($penawaranId)) continue;

                            // varchar → float: bersihkan dulu lalu bulatkan 2 desimal
                            $rawValue = preg_replace('/[^0-9.\-]/', '', (string) $row->revenue_forecast);
                            $currentValue = round((float) $rawValue, 2);
                            
                            $lastTotal = 0;
                            $isNewPenawaran = true;

                            if ($latestLogs->has($penawaranId)) {
                                $lastTotal = round((float) $latestLogs->get($penawaranId)->total, 2);
                                $isNewPenawaran = false;
                            }

                            // DEBUG: Tampilkan perbandingan
                            $this->info("  [{$penawaranId}] isNew={$isNewPenawaran} | FORECAST={$currentValue} | LOG_TOTAL={$lastTotal} | diff=" . abs($currentValue - $lastTotal));

                            // Gunakan TOLERANSI karena varchar vs decimal bisa beda presisi
                            if (!$isNewPenawaran && abs($currentValue - $lastTotal) < 10) {
                                $this->info("    → SKIP (nilai sama/dalam toleransi)");
                                continue; 
                            }

                            // Tentukan status dan selisih
                            if ($isNewPenawaran) {
                                $status = 'penambahan';
                                $revenueDiff = $currentValue;
                                $this->info("    → INSERT (penawaran baru)");
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
                                'no_penawaran'         => $penawaranId,
                                'periode'              => $row->periode,
                                'tanggal_sampling_min' => $row->tanggal_sampling_min,
                                'revenue_forecast'     => $revenueDiff,
                                'status'               => $status,
                                'total'                => $currentValue,
                                'created_at'           => $now,
                            ];

                            // Update nilai di memori PHP
                            $latestLogs->put($penawaranId, (object)['total' => $currentValue]);
                        }

                        if (!empty($insertData)) {
                            DB::table('qsd_forecast_transaction_log')->insert($insertData);
                            $countInsert += count($insertData);
                        }

                    } catch (Throwable $e) {
                        Log::channel('monitor_log_qsd_forecast')->error("[$batchId] Gagal memproses chunk data", [
                            'pesan_error' => $e->getMessage(),
                            'file'        => $e->getFile(),
                            'baris'       => $e->getLine()
                        ]);
                    }
                });

            $this->info("[$batchId] Selesai! Terdeteksi {$countInsert} pergerakan.");
            Log::channel('monitor_log_qsd_forecast')->info("[$batchId] Monitoring selesai", ['total_pergerakan_baru' => $countInsert]);

        } catch (Throwable $e) {
            $this->error("[$batchId] Terjadi kesalahan fatal. Cek file log.");
            Log::channel('monitor_log_qsd_forecast')->critical("[$batchId] KESALAHAN FATAL SISTEM MONITORING", [
                'pesan_error' => $e->getMessage(),
                'file'        => $e->getFile(),
                'baris'       => $e->getLine(),
                'trace'       => $e->getTraceAsString()
            ]);
        }
    }
}