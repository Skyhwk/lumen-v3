<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecalculateQsdLogs extends Command
{
    protected $signature = 'qsd:recalculate-logs {--type=all : revenue, forecast, or all} {--periode= : Format YYYY-MM (kosongkan untuk semua periode)}';
    protected $description = 'Menyesuaikan dan melengkapi data log QSD lama (tanggal dan saldo berjalan) tanpa menghapus data';

    public function handle()
    {
        $type = strtolower($this->option('type') ?? 'all');
        $targetPeriode = $this->option('periode'); // e.g. "2026-09"

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
            $this->adjustForecastLogs($targetPeriode);
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

    private function adjustForecastLogs(?string $targetPeriode)
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

        foreach ($months as $bulan) {
            if (!$bulan) continue;
            [$year, $month] = explode('-', $bulan);

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

        $this->info("  Total forecast logs disesuaikan: {$totalUpdated} baris.");
    }
}
