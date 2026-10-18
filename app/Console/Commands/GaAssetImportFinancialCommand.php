<?php

namespace App\Console\Commands;

use App\Services\GaAsset\GaAssetExcelImportService;
use Illuminate\Console\Command;

class GaAssetImportFinancialCommand extends Command
{
    protected $signature = 'ga-asset:import-financial
                            {path : Path absolut file Excel GA Asset Total}
                            {--dry-run : Preview tanpa simpan ke database}
                            {--memory=2048M : memory_limit PHP untuk file besar}';

    protected $description = 'Import ulang cepat: harga perolehan, tanggal beli, umur manfaat, lalu hitung depresiasi';

    public function handle(GaAssetExcelImportService $service): int
    {
        $path = (string) $this->argument('path');
        if (!is_file($path)) {
            $this->error('File tidak ditemukan: ' . $path);
            return self::FAILURE;
        }

        $memory = (string) $this->option('memory');
        if ($memory !== '') {
            ini_set('memory_limit', $memory);
        }
        set_time_limit(0);

        $commit = !$this->option('dry-run');
        $this->info($commit ? 'Menyimpan update harga & umur…' : 'Preview (dry-run)…');

        $started = microtime(true);
        $result = $service->importFinancialFromPath(
            $path,
            basename($path),
            $commit,
            null,
            'CLI ga-asset:import-financial'
        );
        $elapsed = round(microtime(true) - $started, 1);

        $stats = $result['stats'] ?? [];
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Baris NO CS', $stats['rows_total'] ?? 0],
                ['Diperbarui', $stats['rows_updated'] ?? 0],
                ['Dilewati (kosong)', $stats['rows_skipped'] ?? 0],
                ['Tidak ditemukan', $stats['rows_not_found'] ?? 0],
                ['Gagal', $stats['rows_failed'] ?? 0],
            ]
        );

        if (!empty($result['stats']['depreciation'])) {
            $dep = $result['stats']['depreciation'];
            $this->info(sprintf(
                'Depresiasi: %d aset, %d baris snapshot (tahun s/d %s).',
                $dep['assets_snapshotted'] ?? 0,
                $dep['rows_written'] ?? 0,
                $dep['through_year'] ?? date('Y')
            ));
        }
        if (!empty($result['stats']['depreciation_error'])) {
            $this->warn('Depresiasi gagal: ' . $result['stats']['depreciation_error']);
        }

        $this->info('Selesai dalam ' . $elapsed . ' detik.');
        return ($stats['rows_failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
