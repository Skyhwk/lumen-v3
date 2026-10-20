<?php

namespace App\Console\Commands;

use App\Services\GaAsset\GaAssetDepreciationService;
use App\Services\GaAsset\GaAssetExcelReconcileService;
use Illuminate\Console\Command;

class GaAssetReconcileExcelCommand extends Command
{
    protected $signature = 'ga-asset:reconcile-excel
                            {path : Path absolut file Excel GA Asset Total}
                            {--year=2019 : Tahun fiskal di sheet Total}
                            {--category= : Filter CAT-1, mis. PERLENGKAPAN}
                            {--sub= : Filter CAT-2, mis. KANTOR}
                            {--tolerance=1 : Toleransi selisih rupiah}';

    protected $description = 'Bandingkan sheet Total Excel vs Laporan Depresiasi (database) untuk pilot rekonsiliasi';

    public function handle(GaAssetExcelReconcileService $reconcile, GaAssetDepreciationService $depreciation): int
    {
        $path = (string) $this->argument('path');
        if (!is_file($path)) {
            $this->error('File tidak ditemukan: ' . $path);

            return self::FAILURE;
        }

        $year = (int) $this->option('year');
        $category = $this->option('category');
        $sub = $this->option('sub');
        $tolerance = (string) $this->option('tolerance');

        try {
            $result = $reconcile->compareYear(
                $path,
                $depreciation,
                $year,
                $category !== null && $category !== '' ? (string) $category : null,
                $sub !== null && $sub !== '' ? (string) $sub : null,
                $tolerance
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Rekonsiliasi tahun %d — baris mismatch: %d, footer mismatch: %d',
            $year,
            $result['mismatch_rows'],
            count($result['footer_diffs'])
        ));

        foreach ($result['row_diffs'] as $row) {
            $this->warn(sprintf('%s / %s', $row['category'], $row['sub_category']));
            foreach ($row['diffs'] as $diff) {
                $this->line(sprintf(
                    '  - %s: Excel=%s | Sistem=%s | delta=%s',
                    $diff['field'],
                    $diff['excel'] ?? '-',
                    $diff['system'] ?? '-',
                    $diff['delta'] ?? '-'
                ));
            }
        }

        foreach ($result['footer_diffs'] as $diff) {
            $this->warn(sprintf(
                'Footer %s: Excel=%s | Sistem=%s | delta=%s',
                $diff['field'],
                $diff['excel'],
                $diff['system'],
                $diff['delta']
            ));
        }

        if ($result['ok']) {
            $this->info('OK — angka selaras dalam toleransi.');

            return self::SUCCESS;
        }

        $this->error('Tidak selaras — periksa import, Hitung ulang depresiasi, atau toleransi.');

        return self::FAILURE;
    }
}
