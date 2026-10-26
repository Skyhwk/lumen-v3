<?php

namespace App\Console\Commands;

use App\Support\Greatday\GreatdayAssetPaths;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Salin bulk aset Greatday dari public Intilab-Internal (Super Apps lama) → public/greatday/{folder}/.
 */
class ImportGreatdayAssetsFromInternalCommand extends Command
{
    protected $signature = 'greatday:import-assets-from-internal
                            {--source= : Path absolut ke folder public Intilab-Internal (default: GREATDAY_INTILAB_INTERNAL_PUBLIC)}
                            {--dry-run : Tampilkan rencana tanpa copy}
                            {--force : Timpa file yang sudah ada di greatday/}';

    protected $description = 'Copy manual folder aset legacy Intilab-Internal/public → LUMEN public/greatday/';

    /** @var array<string, string> subfolder relatif di public Internal → asset key greatday */
    private const SOURCE_TO_ASSET_KEY = [
        'android-image/absensi' => GreatdayAssetPaths::KEY_ABSENSI,
        'leave-requests' => GreatdayAssetPaths::KEY_CUTI,
        'android-image/leave-requests' => GreatdayAssetPaths::KEY_CUTI,
        'permission-requests' => GreatdayAssetPaths::KEY_IZIN,
        'android-image/lampiran_izin' => GreatdayAssetPaths::KEY_IZIN,
        'android-image/permission-requests' => GreatdayAssetPaths::KEY_IZIN,
        'attendance-corrections' => GreatdayAssetPaths::KEY_KOREKSI_ABSEN,
        'android-image/attendance-corrections' => GreatdayAssetPaths::KEY_KOREKSI_ABSEN,
        'android-image/koreksi-absen' => GreatdayAssetPaths::KEY_KOREKSI_ABSEN,
        'overtime-reimbursements' => GreatdayAssetPaths::KEY_LEMBUR_REIMBURSE,
        'android-image/overtime-reimbursements' => GreatdayAssetPaths::KEY_LEMBUR_REIMBURSE,
        'event-reports' => GreatdayAssetPaths::KEY_LAPORAN_KEGIATAN,
        'android-image/event-reports' => GreatdayAssetPaths::KEY_LAPORAN_KEGIATAN,
    ];

    public function handle(): int
    {
        $sourceRoot = $this->resolveSourceRoot();
        if ($sourceRoot === null) {
            $this->error('Folder sumber tidak ditemukan.');
            $this->line('Set GREATDAY_INTILAB_INTERNAL_PUBLIC di .env atau pakai --source="D:/PHP/Intilab-Internal/public"');

            return 1;
        }

        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('Sumber: ' . $sourceRoot);
        $this->info('Tujuan: ' . public_path(GreatdayAssetPaths::base()));
        if ($dryRun) {
            $this->warn('Mode dry-run — tidak ada file yang ditulis.');
        }

        $copied = 0;
        $skipped = 0;
        $missingDirs = 0;

        foreach (self::SOURCE_TO_ASSET_KEY as $relativeSource => $assetKey) {
            $srcDir = $sourceRoot . '/' . str_replace('\\', '/', $relativeSource);
            if (!is_dir($srcDir)) {
                $missingDirs++;
                $this->line("  [skip] folder tidak ada: {$relativeSource}");

                continue;
            }

            $destDir = GreatdayAssetPaths::publicDir($assetKey);
            if (!$dryRun && !is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }

            $destRelative = GreatdayAssetPaths::relativeDir($assetKey);
            $this->line('');
            $this->comment("{$relativeSource} → {$destRelative}/");

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $baseName = $file->getBasename();
                if ($baseName === '' || $baseName === '.gitkeep') {
                    continue;
                }

                $destFile = $destDir . DIRECTORY_SEPARATOR . $baseName;
                if (is_file($destFile) && !$force) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("    [dry-run] {$baseName}");
                    $copied++;

                    continue;
                }

                if (@copy($file->getPathname(), $destFile)) {
                    $copied++;
                } else {
                    $this->error("    Gagal copy: {$baseName}");
                }
            }
        }

        $this->newLine();
        $this->info("Selesai. copied={$copied}, skipped_existing={$skipped}, source_folder_missing={$missingDirs}");
        if ($skipped > 0 && !$force) {
            $this->line('Pakai --force untuk menimpa file yang sudah ada.');
        }

        return 0;
    }

    private function resolveSourceRoot(): ?string
    {
        $opt = $this->option('source');
        if (is_string($opt) && trim($opt) !== '') {
            $path = realpath(trim($opt));

            return $path !== false && is_dir($path) ? $path : null;
        }

        $fromEnv = config('greatday.intilab_internal_public');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            $path = realpath(trim($fromEnv));

            return $path !== false && is_dir($path) ? $path : null;
        }

        $guesses = [
            dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'Intilab-Internal' . DIRECTORY_SEPARATOR . 'public',
            'D:' . DIRECTORY_SEPARATOR . 'PHP' . DIRECTORY_SEPARATOR . 'Intilab-Internal' . DIRECTORY_SEPARATOR . 'public',
        ];

        foreach ($guesses as $guess) {
            if (is_dir($guess)) {
                return realpath($guess) ?: $guess;
            }
        }

        return null;
    }
}
