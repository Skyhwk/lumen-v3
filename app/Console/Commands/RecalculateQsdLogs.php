<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecalculateQsdLogs extends Command
{
    protected $signature = 'qsd:recalculate-logs {--type=all : revenue, forecast, or all} {--periode= : Format YYYY-MM (kosongkan untuk semua periode)} {--balance-forecast : Seimbangkan saldo penawaran non-aktif yang minus agar sama dengan forecast_sp}';
    protected $description = 'Menyesuaikan dan melengkapi data log QSD lama (tanggal, saldo berjalan, dan balancing forecast) tanpa menghapus data';

    public function handle()
    {
        $type = strtolower($this->option('type') ?? 'all');
        $targetPeriode = $this->option('periode'); // e.g. "2026-10"
        $balanceForecast = true; // Selalu aktifkan penyeimbangan forecast agar log cocok dengan live data

        $this->info("=== MEMULAI PENYESUAIAN DATA LOG QSD ===");
        if ($targetPeriode) {
            $this->info("Target Periode: {$targetPeriode}");
        } else {
            $this->info("Target Periode: SEMUA BULAN");
        }

        if ($type === 'revenue' || $type === 'all') {
            $this->adjustRevenueLogs($targetPeriode);
        }

        if ($type === 'forecast' || $type === 'all') {
            $this->adjustForecastLogs($targetPeriode, $balanceForecast);
        }

        $this->info("\n=== PENYESUAIAN DATA LOG QSD SELESAI ===");
    }

    private function adjustRevenueLogs(?string $targetPeriode)
    {
        $this->info("\n[1/2] Menyesuaikan qsd_revenue_transaction_log...");

        // 1. Lengkapi tanggal_kelompok yang NULL
        $nullCount = DB::table('qsd_revenue_transaction_log')->whereNull('tanggal_kelompok')->count();
        if ($nullCount > 0) {
            $this->line("  - Ditemukan {$nullCount} baris dengan tanggal_kelompok NULL. Melengkapi dari daily_qsd...");
            $nullRows = DB::table('qsd_revenue_transaction_log')->whereNull('tanggal_kelompok')->get(['id', 'no_order', 'periode', 'created_at']);
            
            $fixedDateCount = 0;
            foreach ($nullRows as $row) {
                $dq = DB::table('daily_qsd')
                    ->where('no_order', $row->no_order)
                    ->whereNotNull('tanggal_kelompok')
                    ->value('tanggal_kelompok');

                $resolvedDate = $dq;
                if (!$resolvedDate && !empty($row->periode) && preg_match('/^\d{4}-\d{2}$/', $row->periode)) {
                    $resolvedDate = $row->periode . '-01';
                }
                if (!$resolvedDate && $row->created_at) {
                    $resolvedDate = date('Y-m-d', strtotime($row->created_at));
                }

                if ($resolvedDate) {
                    DB::table('qsd_revenue_transaction_log')->where('id', $row->id)->update([
                        'tanggal_kelompok' => $resolvedDate,
                    ]);
                    $fixedDateCount++;
                }
            }
            $this->line("  - Berhasil melengkapi {$fixedDateCount} baris tanggal_kelompok.");
        }

        // 2. Ambil daftar bulan yang akan diproses
        $monthQuery = DB::table('qsd_revenue_transaction_log')
            ->selectRaw("DISTINCT DATE_FORMAT(tanggal_kelompok, '%Y-%m') as bulan_key")
            ->whereNotNull('tanggal_kelompok');

        if ($targetPeriode) {
            $monthQuery->whereRaw("DATE_FORMAT(tanggal_kelompok, '%Y-%m') = ?", [$targetPeriode]);
        }

        $months = $monthQuery->orderBy('bulan_key')->pluck('bulan_key');

        $totalUpdated = 0;

        foreach ($months as $bulan) {
            if (!$bulan) continue;
            [$year, $month] = explode('-', $bulan);

            // 2.1 AUTO-BALANCING REVENUE: Netralkan order non-aktif yang saldonya minus (orphan reduction)
            $activeOrders = DB::table('daily_qsd')
                ->whereNotNull('tanggal_kelompok')
                ->whereYear('tanggal_kelompok', (int)$year)
                ->whereMonth('tanggal_kelompok', (int)$month)
                ->pluck('no_order')
                ->unique()
                ->all();

            $netPerOrder = DB::table('qsd_revenue_transaction_log')
                ->whereYear('tanggal_kelompok', (int)$year)
                ->whereMonth('tanggal_kelompok', (int)$month)
                ->select('no_order', DB::raw('SUM(CASE WHEN status = "penambahan" THEN revenue ELSE -revenue END) as net_balance'))
                ->groupBy('no_order')
                ->get();

            $revReversals = 0;
            $now = Carbon::now('Asia/Jakarta');

            foreach ($netPerOrder as $orderNet) {
                $noOrder = $orderNet->no_order;
                $netBalance = (float)$orderNet->net_balance;

                if (!in_array($noOrder, $activeOrders) && $netBalance < -0.01) {
                    $reversalAmount = round(abs($netBalance), 2);

                    $lastLog = DB::table('qsd_revenue_transaction_log')
                        ->where('no_order', $noOrder)
                        ->whereYear('tanggal_kelompok', (int)$year)
                        ->whereMonth('tanggal_kelompok', (int)$month)
                        ->orderByDesc('id')
                        ->first();

                    DB::table('qsd_revenue_transaction_log')->insert([
                        'no_order'         => $noOrder,
                        'periode'          => $lastLog->periode ?? null,
                        'tanggal_kelompok' => $lastLog->tanggal_kelompok ?? ($bulan . '-01'),
                        'revenue'          => $reversalAmount,
                        'status'           => 'penambahan',
                        'total'            => 0.00,
                        'is_revisi'        => 1,
                        'created_at'       => $now,
                    ]);

                    $revReversals++;
                    $this->line(sprintf("    [Balancing Revenue] Order %s di bulan %s saldonya %s -> ditambah koreksi +%s",
                        $noOrder,
                        $bulan,
                        number_format($netBalance, 2),
                        number_format($reversalAmount, 2)
                    ));
                }
            }

            if ($revReversals > 0) {
                $this->line("    Total {$revReversals} order non-aktif yang minus telah diseimbangkan ke 0.");
            }

            $rows = DB::table('qsd_revenue_transaction_log')
                ->whereYear('tanggal_kelompok', (int)$year)
                ->whereMonth('tanggal_kelompok', (int)$month)
                ->orderBy('id', 'asc')
                ->get();

            $runningGrandTotal = 0.0;
            $seenOrders = [];

            foreach ($rows as $row) {
                $rev = (float)$row->revenue;
                $isRevisi = isset($seenOrders[$row->no_order]) ? 1 : 0;
                $seenOrders[$row->no_order] = true;

                if ($row->status === 'penambahan') {
                    $runningGrandTotal += $rev;
                } else {
                    $runningGrandTotal -= $rev;
                }

                $runningGrandTotal = round($runningGrandTotal, 2);

                DB::table('qsd_revenue_transaction_log')->where('id', $row->id)->update([
                    'total'     => $runningGrandTotal,
                    'is_revisi' => $isRevisi,
                ]);

                $totalUpdated++;
            }

            $this->line(sprintf("  - Bulan %s: %d baris diperbarui. Saldo Berjalan Akhir: %s", 
                $bulan, 
                count($rows), 
                number_format($runningGrandTotal, 2)
            ));
        }

        $this->info("  Total revenue logs disesuaikan: {$totalUpdated} baris.");
    }

    private function adjustForecastLogs(?string $targetPeriode, bool $balanceForecast = true)
    {
        $this->info("\n[2/2] Menyesuaikan qsd_forecast_transaction_log...");

        // 1. Lengkapi tanggal_sampling_min yang NULL
        $nullCount = DB::table('qsd_forecast_transaction_log')->whereNull('tanggal_sampling_min')->count();
        if ($nullCount > 0) {
            $this->line("  - Ditemukan {$nullCount} baris dengan tanggal_sampling_min NULL. Melengkapi...");
            $nullRows = DB::table('qsd_forecast_transaction_log')->whereNull('tanggal_sampling_min')->get(['id', 'no_penawaran', 'periode', 'created_at']);
            
            $fixedDateCount = 0;
            foreach ($nullRows as $row) {
                $spDate = DB::table('forecast_sp')
                    ->where('no_quotation', $row->no_penawaran)
                    ->whereNotNull('tanggal_sampling_min')
                    ->value('tanggal_sampling_min');

                $resolvedDate = $spDate;
                if (!$resolvedDate && !empty($row->periode) && preg_match('/^\d{4}-\d{2}$/', $row->periode)) {
                    $resolvedDate = $row->periode . '-01';
                }
                if (!$resolvedDate && $row->created_at) {
                    $resolvedDate = date('Y-m-d', strtotime($row->created_at));
                }

                if ($resolvedDate) {
                    DB::table('qsd_forecast_transaction_log')->where('id', $row->id)->update([
                        'tanggal_sampling_min' => $resolvedDate,
                    ]);
                    $fixedDateCount++;
                }
            }
            $this->line("  - Berhasil melengkapi {$fixedDateCount} baris tanggal_sampling_min.");
        }

        // 2. Ambil daftar bulan yang akan diproses
        $monthQuery = DB::table('qsd_forecast_transaction_log')
            ->selectRaw("DISTINCT DATE_FORMAT(tanggal_sampling_min, '%Y-%m') as bulan_key")
            ->whereNotNull('tanggal_sampling_min');

        if ($targetPeriode) {
            $monthQuery->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$targetPeriode]);
        }

        $months = $monthQuery->orderBy('bulan_key')->pluck('bulan_key');

        $totalUpdated = 0;
        $totalBalanced = 0;

        foreach ($months as $bulan) {
            if (!$bulan) continue;
            [$year, $month] = explode('-', $bulan);

            // 2.1 AUTO-BALANCING: Seimbangkan penawaran non-aktif yang minus di bulan ini
            if ($balanceForecast) {
                $activeQuotations = DB::table('forecast_sp')
                    ->whereNotNull('tanggal_sampling_min')
                    ->whereYear('tanggal_sampling_min', (int)$year)
                    ->whereMonth('tanggal_sampling_min', (int)$month)
                    ->pluck('no_quotation')
                    ->unique()
                    ->all();

                $netPerQuotation = DB::table('qsd_forecast_transaction_log')
                    ->whereYear('tanggal_sampling_min', (int)$year)
                    ->whereMonth('tanggal_sampling_min', (int)$month)
                    ->select('no_penawaran', DB::raw('SUM(CASE WHEN status = "penambahan" THEN revenue_forecast ELSE -revenue_forecast END) as net_balance'))
                    ->groupBy('no_penawaran')
                    ->get();

                $monthReversals = 0;
                $now = Carbon::now('Asia/Jakarta');

                foreach ($netPerQuotation as $quoteNet) {
                    $noPenawaran = $quoteNet->no_penawaran;
                    $netBalance = (float)$quoteNet->net_balance;

                    // Jika penawaran sudah non-aktif di forecast_sp dan saldonya di log negatif (minus):
                    if (!in_array($noPenawaran, $activeQuotations) && $netBalance < -0.01) {
                        $reversalAmount = round(abs($netBalance), 2);

                        $lastLog = DB::table('qsd_forecast_transaction_log')
                            ->where('no_penawaran', $noPenawaran)
                            ->whereYear('tanggal_sampling_min', (int)$year)
                            ->whereMonth('tanggal_sampling_min', (int)$month)
                            ->orderByDesc('id')
                            ->first();

                        DB::table('qsd_forecast_transaction_log')->insert([
                            'no_penawaran'         => $noPenawaran,
                            'periode'              => $lastLog->periode ?? null,
                            'tanggal_sampling_min' => $lastLog->tanggal_sampling_min ?? "$bulan-01",
                            'revenue_forecast'     => $reversalAmount,
                            'status'               => 'penambahan',
                            'total'                => 0, // Akan dihitung ulang pada loop di bawah
                            'forecast_order'       => 0,
                            'created_at'           => $now,
                        ]);

                        $monthReversals++;
                        $totalBalanced++;
                    }
                }

                if ($monthReversals > 0) {
                    $this->line("  - Bulan {$bulan}: Menyeimbangkan {$monthReversals} penawaran non-aktif yang minus.");
                }
            }

            // 2.2 Hitung ulang saldo berjalan kumulatif (running total)
            $rows = DB::table('qsd_forecast_transaction_log')
                ->whereYear('tanggal_sampling_min', (int)$year)
                ->whereMonth('tanggal_sampling_min', (int)$month)
                ->orderBy('id', 'asc')
                ->get();

            $runningGrandTotal = 0.0;

            foreach ($rows as $row) {
                $rev = (float)$row->revenue_forecast;

                if ($row->status === 'penambahan') {
                    $runningGrandTotal += $rev;
                } else {
                    $runningGrandTotal -= $rev;
                }

                $runningGrandTotal = round($runningGrandTotal, 2);

                DB::table('qsd_forecast_transaction_log')->where('id', $row->id)->update([
                    'total' => $runningGrandTotal,
                ]);

                $totalUpdated++;
            }

            $this->line(sprintf("  - Bulan %s: %d baris diperbarui. Saldo Berjalan Akhir: %s", 
                $bulan, 
                count($rows), 
                number_format($runningGrandTotal, 2)
            ));
        }

        $this->info("  Total forecast logs disesuaikan: {$totalUpdated} baris (Penyeimbang dibuat: {$totalBalanced} baris).");
    }
}
