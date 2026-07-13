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
            // STEP 1: Ambil SALDO BERJALAN (Grand Total Terakhir) di bulan ini
            // ================================================================
            $currentGrandTotal = DB::table('qsd_forecast_transaction_log')
                ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->orderByDesc('id') // Ambil transaksi paling terakhir
                ->value('total');

            $currentGrandTotal = $currentGrandTotal ? (float) $currentGrandTotal : 0.0;

            // ================================================================
            // STEP 1.5: Ambil riwayat NILAI PER PENAWARAN di bulan ini
            // Kita hitung menggunakan SUM(Penambahan) - SUM(Pengurangan)
            // dan mengambil MAX(forecast_order) untuk tahu status terakhirnya
            // ================================================================
            $latestLogs = DB::table('qsd_forecast_transaction_log')
                ->select(
                    'no_penawaran',
                    'periode',
                    DB::raw('MAX(tanggal_sampling_min) as tanggal_sampling_min'),
                    DB::raw('SUM(CASE WHEN status = "penambahan" THEN revenue_forecast ELSE -revenue_forecast END) as order_total'),
                    DB::raw('MAX(forecast_order) as is_ordered')
                )
                ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->groupBy('no_penawaran', 'periode')
                ->get()
                ->keyBy(function ($item) {
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
            // STEP 3: Cek Perubahan Nilai & Insert (Update Saldo Berjalan)
            // ================================================================
            foreach ($groupedForecasts as $uniqueKey => $data) {
                $activePenawaran[] = $uniqueKey;

                $currentValue = round($data['total_revenue'], 2);
                $lastOrderTotal = 0;
                $isNewPenawaran = true;
                $wasOrdered = false; 

                if ($latestLogs->has($uniqueKey)) {
                    $log = $latestLogs->get($uniqueKey);
                    $lastOrderTotal = round((float) $log->order_total, 2);
                    $wasOrdered = (int) $log->is_ordered === 1;
                    $isNewPenawaran = false;
                }

                if (!$isNewPenawaran && !$wasOrdered && abs($currentValue - $lastOrderTotal) < 0.01) {
                    continue; 
                }

                if ($isNewPenawaran || $wasOrdered) {
                    $status = 'penambahan';
                    $revenueDiff = $currentValue - $lastOrderTotal; 
                    
                    // UPDATE SALDO BERJALAN
                    $currentGrandTotal += $revenueDiff;
                    
                } elseif ($currentValue > $lastOrderTotal) {
                    $status = 'penambahan';
                    $revenueDiff = round($currentValue - $lastOrderTotal, 2);
                    
                    // UPDATE SALDO BERJALAN
                    $currentGrandTotal += $revenueDiff;
                    
                } else {
                    $status = 'pengurangan';
                    $revenueDiff = round($lastOrderTotal - $currentValue, 2);
                    
                    // UPDATE SALDO BERJALAN
                    $currentGrandTotal -= $revenueDiff;
                }

                $insertData[] = [
                    'no_penawaran'         => $data['no_quotation'],
                    'periode'              => $data['periode'],
                    'tanggal_sampling_min' => $data['tanggal_sampling_min'],
                    'revenue_forecast'     => $revenueDiff,
                    'status'               => $status,
                    'total'                => $currentGrandTotal, // <-- Simpan Saldo Berjalan
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
                $lastOrderTotal = round((float) $logEntry->order_total, 2);
                
                // Jika sudah di-flag order (1) ATAU saldonya sudah 0, abaikan
                if ((int)$logEntry->is_ordered === 1 || $lastOrderTotal <= 0) {
                    continue;
                }

                // KURANGI SALDO BERJALAN
                $currentGrandTotal -= $lastOrderTotal;

                $insertData[] = [
                    'no_penawaran'         => $logEntry->no_penawaran,
                    'periode'              => $logEntry->periode,
                    'tanggal_sampling_min' => $logEntry->tanggal_sampling_min,
                    'revenue_forecast'     => $lastOrderTotal, 
                    'status'               => 'pengurangan',
                    'total'                => $currentGrandTotal, // <-- Simpan Saldo Berjalan
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