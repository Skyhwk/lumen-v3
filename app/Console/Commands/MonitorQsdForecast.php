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
    protected $description = 'Monitor pergerakan nilai forecast_sp setiap 5 menit (2-sisi: tambah & hapus)';

    /**
     * Parse revenue_forecast dari varchar ke float.
     * Handle format: "2000000", "2.000.000", "Rp 2.000.000", "2,000,000.50"
     */
    private function parseRevenueForecast($raw): float
    {
        $str = trim((string) $raw);

        if ($str === '' || $str === null) {
            return 0.0;
        }

        // Hapus prefix non-numerik (Rp, IDR, $, dll)
        $str = preg_replace('/^[^0-9\-]+/', '', $str);

        // Deteksi format: apakah ada koma sebagai desimal (Indonesia) atau titik (International)
        // Contoh Indonesia: "2.000.000" atau "2.000.000,50"
        // Contoh International: "2,000,000" atau "2,000,000.50"

        if (preg_match('/,\d{1,2}$/', $str)) {
            // Koma di akhir dengan 1-2 digit = desimal (format Indonesia)
            // "2.000.000,50" → hapus titik → ganti koma jadi titik
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            // Format lain: hapus semua pemisah ribuan (titik dan koma)
            // "2.000.000" → "2000000"
            // "2,000,000" → "2000000"
            $str = str_replace(['.', ','], '', $str);
        }

        // Bersihkan sisa karakter non-numerik
        $str = preg_replace('/[^0-9.\-]/', '', $str);

        return round((float) $str, 2);
    }

    public function handle()
    {
        $batchId = uniqid('MONITOR-FC-');
        
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->startOfDay();
        $endOfMonth   = $now->copy()->endOfMonth()->endOfDay();

        $this->info("[$batchId] Memulai monitoring pergerakan data forecast...");
        Log::channel('monitor_log_qsd_forecast')->info("[$batchId] Memulai monitoring qsd_forecast", ['waktu' => $now->toDateTimeString()]);

        try {
            // ================================================================
            // STEP 1: Ambil LOG TERAKHIR per no_penawaran bulan berjalan
            // Termasuk forecast_order agar STEP 3 bisa cek apakah sudah di-flag
            // ================================================================
            $latestLogs = DB::table('qsd_forecast_transaction_log as t1')
                ->select('t1.no_penawaran', 't1.total', 't1.periode', 't1.tanggal_sampling_min', 't1.forecast_order')
                ->whereBetween('t1.tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->whereRaw('t1.id = (
                        SELECT MAX(t2.id)
                        FROM qsd_forecast_transaction_log t2
                        WHERE t2.no_penawaran = t1.no_penawaran
                          AND t2.tanggal_sampling_min BETWEEN ? AND ?
                    )', [$startOfMonth, $endOfMonth])
                ->get()
                ->keyBy('no_penawaran');

            $countInsert = 0;

            // Kumpulkan no_penawaran yang masih ada di forecast_sp bulan ini
            $activePenawaran = collect();

            // ================================================================
            // STEP 2 (SISI 1): Cek forecast_sp → bandingkan dengan log
            // ================================================================
            DB::table('forecast_sp')
                ->whereNotNull('tanggal_sampling_min')
                ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->orderBy('no_quotation')
                ->chunk(500, function ($records) use ($latestLogs, $now, &$countInsert, &$activePenawaran, $batchId) {
                    
                    try {
                        $insertData = [];

                        foreach ($records as $row) {
                            $penawaranId = $row->no_quotation;
                            
                            if (empty($penawaranId)) continue;

                            $activePenawaran->push($penawaranId);

                            // Parse varchar revenue_forecast dengan aman
                            $currentValue = $this->parseRevenueForecast($row->revenue_forecast);
                            
                            $lastTotal = 0;
                            $isNewPenawaran = true;

                            if ($latestLogs->has($penawaranId)) {
                                $lastTotal = round((float) $latestLogs->get($penawaranId)->total, 2);
                                $isNewPenawaran = false;
                            }

                            // DEBUG: tampilkan RAW varchar + hasil parse
                            $this->info("  [{$penawaranId}] isNew={$isNewPenawaran} | RAW='{$row->revenue_forecast}' | PARSED={$currentValue} | LOG={$lastTotal} | diff=" . abs($currentValue - $lastTotal));

                            if (!$isNewPenawaran && abs($currentValue - $lastTotal) < 0.01) {
                                $this->info("    → SKIP (nilai sama)");
                                continue; 
                            }

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
                                'forecast_order'       => 0,  // Masih aktif, belum jadi order
                                'created_at'           => $now,
                            ];

                            $latestLogs->put($penawaranId, (object)[
                                'total' => $currentValue,
                                'forecast_order' => 0,
                            ]);
                        }

                        if (!empty($insertData)) {
                            DB::table('qsd_forecast_transaction_log')->insert($insertData);
                            $countInsert += count($insertData);
                        }

                    } catch (Throwable $e) {
                        Log::channel('monitor_log_qsd_forecast')->error("[$batchId] Gagal memproses chunk forecast_sp", [
                            'pesan' => $e->getMessage(),
                            'baris' => $e->getLine()
                        ]);
                    }
                });

            // ================================================================
            // STEP 3 (SISI 2): Cek log → cari yang SUDAH TIDAK ADA di forecast_sp
            // Update forecast_order = 1 (true) untuk semua log entry bulan ini
            // ================================================================
            $this->info("[$batchId] Mengecek penawaran yang sudah dihapus dari forecast_sp...");

            $countOrdered = 0;

            foreach ($latestLogs as $noPenawaran => $logEntry) {
                // Sudah di-flag sebelumnya → skip
                if (!empty($logEntry->forecast_order)) {
                    continue;
                }

                // Jika no_penawaran ini TIDAK ada di forecast_sp bulan ini → sudah jadi order
                if (!$activePenawaran->contains($noPenawaran)) {
                    $lastTotal = round((float) $logEntry->total, 2);
                    $this->info("  [{$noPenawaran}] ORDERED | LOG_TOTAL={$lastTotal} → set forecast_order=1");

                    // Update SEMUA log entry bulan ini untuk no_penawaran ini
                    // Gunakan integer 0 bukan boolean false (MySQL tinyint)
                    $affected = DB::table('qsd_forecast_transaction_log')
                        ->where('no_penawaran', $noPenawaran)
                        ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                        ->where('forecast_order', 0)
                        ->update(['forecast_order' => 1]);

                    $this->info("    → Updated {$affected} rows");
                    $countOrdered++;
                }
            }

            if ($countOrdered > 0) {
                $this->info("[$batchId] Tercatat {$countOrdered} penawaran sudah menjadi order.");
            }

            $this->info("[$batchId] Selesai! Terdeteksi {$countInsert} pergerakan, {$countOrdered} ordered.");
            Log::channel('monitor_log_qsd_forecast')->info("[$batchId] Monitoring selesai", [
                'pergerakan' => $countInsert,
                'ordered'    => $countOrdered,
            ]);

        } catch (Throwable $e) {
            $this->error("[$batchId] Terjadi kesalahan fatal. Cek file log.");
            Log::channel('monitor_log_qsd_forecast')->critical("[$batchId] KESALAHAN FATAL", [
                'pesan' => $e->getMessage(),
                'file'  => $e->getFile(),
                'baris' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}