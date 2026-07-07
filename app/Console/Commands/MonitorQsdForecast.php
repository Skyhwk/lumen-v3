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
            // STEP 1: Ambil LOG TERAKHIR berdasarkan Kunci Ganda (Penawaran + Periode)
            // ================================================================
            $latestLogs = DB::table('qsd_forecast_transaction_log as t1')
                ->select('t1.no_penawaran', 't1.total', 't1.periode', 't1.tanggal_sampling_min', 't1.forecast_order')
                ->whereBetween('t1.tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->whereRaw('t1.id = (
                        SELECT MAX(t2.id)
                        FROM qsd_forecast_transaction_log t2
                        WHERE t2.no_penawaran = t1.no_penawaran
                        -- Mencegah cross-month error: Pastikan periodenya juga sama!
                        AND (t2.periode = t1.periode OR (t2.periode IS NULL AND t1.periode IS NULL))
                        AND t2.tanggal_sampling_min BETWEEN ? AND ?
                    )', [$startOfMonth, $endOfMonth])
                ->get()
                ->keyBy(function ($item) {
                    // Gabungkan nomor penawaran dan periode sebagai ID Unik
                    return $item->no_penawaran . '|' . $item->periode;
                });

            $countInsert = 0;
            $activePenawaran = [];
            $insertData = [];

            // ================================================================
            // STEP 2: Tarik & Grouping di Memori berdasarkan Kunci Ganda
            // ================================================================
            $groupedForecasts = [];

            DB::table('forecast_sp')
                ->select('no_quotation', 'periode', 'tanggal_sampling_min', 'revenue_forecast')
                ->whereNotNull('tanggal_sampling_min')
                ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->orderBy('no_quotation')
                ->chunk(1000, function ($records) use (&$groupedForecasts) {
                    foreach ($records as $row) {
                        $penawaranId = $row->no_quotation;
                        if (empty($penawaranId)) continue;

                        // ID Unik untuk memisahkan 1 quotation yang memiliki banyak periode
                        $uniqueKey = $penawaranId . '|' . $row->periode;
                        
                        $parsedValue = $this->parseRevenueForecast($row->revenue_forecast);

                        if (!isset($groupedForecasts[$uniqueKey])) {
                            $groupedForecasts[$uniqueKey] = [
                                'no_quotation'         => $penawaranId,
                                'periode'              => $row->periode,
                                'tanggal_sampling_min' => $row->tanggal_sampling_min,
                                'total_revenue'        => 0,
                            ];
                        }

                        $groupedForecasts[$uniqueKey]['total_revenue'] += $parsedValue;
                    }
                });

            // ================================================================
            // STEP 3: Cek Perubahan Nilai & Insert
            // ================================================================
            foreach ($groupedForecasts as $uniqueKey => $data) {
                $activePenawaran[] = $uniqueKey;

                $currentValue = round($data['total_revenue'], 2);
                $lastTotal = 0;
                $isNewPenawaran = true;
                $wasOrdered = false; 

                if ($latestLogs->has($uniqueKey)) {
                    $log = $latestLogs->get($uniqueKey);
                    $lastTotal = round((float) $log->total, 2);
                    $wasOrdered = (int) $log->forecast_order === 1;
                    $isNewPenawaran = false;
                }

                if (!$isNewPenawaran && !$wasOrdered && abs($currentValue - $lastTotal) < 0.01) {
                    continue; 
                }

                if ($isNewPenawaran || $wasOrdered) {
                    $status = 'penambahan';
                    $revenueDiff = $currentValue - $lastTotal; 
                } elseif ($currentValue > $lastTotal) {
                    $status = 'penambahan';
                    $revenueDiff = round($currentValue - $lastTotal, 2);
                } else {
                    $status = 'pengurangan';
                    $revenueDiff = round($lastTotal - $currentValue, 2);
                }

                $insertData[] = [
                    'no_penawaran'         => $data['no_quotation'],
                    'periode'              => $data['periode'], // Periode tetap terjaga
                    'tanggal_sampling_min' => $data['tanggal_sampling_min'],
                    'revenue_forecast'     => $revenueDiff,
                    'status'               => $status,
                    'total'                => $currentValue,
                    'forecast_order'       => 0,
                    'created_at'           => $now,
                ];
            }

            // ================================================================
            // STEP 4: Deteksi Status Berubah Menjadi Order (Hard Delete)
            // ================================================================
            $deletedQuotations = $latestLogs->except($activePenawaran);
            $countOrdered = 0;

            foreach ($deletedQuotations as $uniqueKey => $logEntry) {
                if (!empty($logEntry->forecast_order) || round((float)$logEntry->total, 2) == 0) {
                    continue;
                }

                $lastTotal = round((float) $logEntry->total, 2);

                $insertData[] = [
                    'no_penawaran'         => $logEntry->no_penawaran,
                    'periode'              => $logEntry->periode,
                    'tanggal_sampling_min' => $logEntry->tanggal_sampling_min,
                    'revenue_forecast'     => $lastTotal, // Ditarik seluruh sisa saldonya
                    'status'               => 'pengurangan',
                    'total'                => 0, // Saldo menjadi 0
                    'forecast_order'       => 1, // FLAG
                    'created_at'           => $now,
                ];

                $countOrdered++;
            }

            // ================================================================
            // STEP 5: Batch Insert
            // ================================================================
            if (!empty($insertData)) {
                $chunks = array_chunk($insertData, 500);
                foreach ($chunks as $chunk) {
                    DB::table('qsd_forecast_transaction_log')->insert($chunk);
                    $countInsert += count($chunk);
                }
            }

            $this->info("[$batchId] Selesai! Insert: " . ($countInsert - $countOrdered) . " revisi, {$countOrdered} closed order.");

        } catch (\Throwable $e) {
            // Tampilkan detail error langsung ke terminal
            $this->error("[$batchId] Terjadi kesalahan fatal: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . " (Baris: " . $e->getLine() . ")");
            
            // Tetap simpan ke log
            Log::channel('monitor_log_qsd_forecast')->critical("[$batchId] KESALAHAN FATAL", [
                'pesan' => $e->getMessage(),
                'file'  => $e->getFile(),
                'baris' => $e->getLine()
            ]);
        }
    }
}