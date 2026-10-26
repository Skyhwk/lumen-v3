<?php

namespace App\Console\Commands;

use App\Models\Greatday\AbsensiAndroid;
use App\Models\Greatday\AttendanceCorrection as AppsAttendanceCorrection;
use App\Models\Greatday\LeaveRequest as AppsLeaveRequest;
use App\Models\Greatday\PermissionRequest as AppsPermissionRequest;
use App\Models\Hr\HrAttendanceCorrectionDetail;
use App\Models\Hr\HrLeaveDetail;
use App\Models\Hr\HrPermissionDetail;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Support\Greatday\GreatdayAssetPaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncGreatdayFormAttachmentFilesCommand extends Command
{
    protected $signature = 'greatday:sync-form-attachment-files
                            {--dry-run : Hanya tampilkan rencana copy, tanpa menulis file}';

    protected $description = 'Salin lampiran formulir legacy ke public/greatday/{folder}/';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $roots = config('greatday.form_attachment_legacy_roots', []);
        if ($roots === []) {
            $this->warn('form_attachment_legacy_roots kosong. Set GREATDAY_LEGACY_PUBLIC_ROOT(S) di .env.');
        }

        $storedByAsset = $this->collectStoredValues();
        $copied = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($storedByAsset as $assetKey => $values) {
            $destRelative = GreatdayAssetPaths::relativeDir($assetKey);
            foreach ($values as $stored) {
                foreach (HrFormAttachmentStorage::decodeStoredNames($stored) as $name) {
                    if ($name === '' || str_contains($name, '://') || str_contains($name, '/')) {
                        continue;
                    }

                    $destAbs = GreatdayAssetPaths::publicDir($assetKey) . DIRECTORY_SEPARATOR . $name;
                    if (is_file($destAbs)) {
                        $skipped++;

                        continue;
                    }

                    $source = HrFormAttachmentStorage::findLegacySourceFile($name, $assetKey);
                    if ($source === null) {
                        $missing++;
                        $this->line("  [missing] {$destRelative}/{$name}");

                        continue;
                    }

                    if ($dryRun) {
                        $this->info("[dry-run] copy {$source} → {$destAbs}");
                        $copied++;

                        continue;
                    }

                    $destDir = dirname($destAbs);
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0777, true);
                    }

                    if (@copy($source, $destAbs)) {
                        $copied++;
                        $this->info("Copied {$name} → {$destRelative}/{$name}");
                    } else {
                        $this->error("Gagal copy {$source}");
                    }
                }
            }
        }

        $selfieNames = AbsensiAndroid::query()
            ->whereNotNull('selfie')
            ->where('selfie', '!=', '')
            ->distinct()
            ->pluck('selfie')
            ->all();

        foreach ($selfieNames as $name) {
            $name = (string) $name;
            if ($name === '' || str_contains($name, '/')) {
                continue;
            }
            $destAbs = GreatdayAssetPaths::publicDir(GreatdayAssetPaths::KEY_ABSENSI) . DIRECTORY_SEPARATOR . $name;
            if (is_file($destAbs)) {
                $skipped++;

                continue;
            }
            $source = HrFormAttachmentStorage::findLegacySourceFile($name, GreatdayAssetPaths::KEY_ABSENSI);
            if ($source === null) {
                $missing++;
                $this->line('  [missing absensi] ' . GreatdayAssetPaths::relativeDir(GreatdayAssetPaths::KEY_ABSENSI) . '/' . $name);

                continue;
            }
            if ($dryRun) {
                $this->info("[dry-run] copy {$source} → {$destAbs}");
                $copied++;

                continue;
            }
            $destDir = dirname($destAbs);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0777, true);
            }
            if (@copy($source, $destAbs)) {
                $copied++;
            }
        }

        $this->newLine();
        $this->info("Selesai. copied={$copied}, already_present={$skipped}, missing_source={$missing}");

        return 0;
    }

    /**
     * @return array<string, list<string|null>>
     */
    private function collectStoredValues(): array
    {
        $out = [
            GreatdayAssetPaths::KEY_CUTI => [],
            GreatdayAssetPaths::KEY_IZIN => [],
            GreatdayAssetPaths::KEY_KOREKSI_ABSEN => [],
        ];

        $out[GreatdayAssetPaths::KEY_CUTI] = array_merge(
            $out[GreatdayAssetPaths::KEY_CUTI],
            AppsLeaveRequest::query()->whereNotNull('attachment')->pluck('attachment')->all()
        );

        $out[GreatdayAssetPaths::KEY_IZIN] = array_merge(
            $out[GreatdayAssetPaths::KEY_IZIN],
            AppsPermissionRequest::query()->whereNotNull('attachment')->pluck('attachment')->all()
        );

        $out[GreatdayAssetPaths::KEY_KOREKSI_ABSEN] = array_merge(
            $out[GreatdayAssetPaths::KEY_KOREKSI_ABSEN],
            AppsAttendanceCorrection::query()->whereNotNull('attachment')->pluck('attachment')->all()
        );

        $out[GreatdayAssetPaths::KEY_CUTI] = array_merge(
            $out[GreatdayAssetPaths::KEY_CUTI],
            HrLeaveDetail::query()->whereNotNull('attachment_path')->pluck('attachment_path')->all()
        );

        $out[GreatdayAssetPaths::KEY_IZIN] = array_merge(
            $out[GreatdayAssetPaths::KEY_IZIN],
            HrPermissionDetail::query()->whereNotNull('attachment_path')->pluck('attachment_path')->all()
        );

        $out[GreatdayAssetPaths::KEY_KOREKSI_ABSEN] = array_merge(
            $out[GreatdayAssetPaths::KEY_KOREKSI_ABSEN],
            HrAttendanceCorrectionDetail::query()->whereNotNull('attachment_path')->pluck('attachment_path')->all()
        );

        try {
            if (DB::getSchemaBuilder()->hasTable('form_detail')) {
                $portalIzin = DB::table('form_detail')
                    ->whereNotNull('filename')
                    ->pluck('filename')
                    ->all();
                $out[GreatdayAssetPaths::KEY_IZIN] = array_merge($out[GreatdayAssetPaths::KEY_IZIN], $portalIzin);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }
}
