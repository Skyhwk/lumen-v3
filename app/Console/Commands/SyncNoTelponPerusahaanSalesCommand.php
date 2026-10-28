<?php

namespace App\Console\Commands;

use App\Helpers\MasterKaryawanCompanyPhoneHelper;
use App\Models\MasterKaryawan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncNoTelponPerusahaanSalesCommand extends Command
{
    protected $signature = 'hrd:sync-no-telpon-perusahaan-sales {--dry-run : Tampilkan perubahan tanpa menyimpan}';

    protected $description = 'Isi no_telpon_perusahaan dari no_telpon untuk Sales Officer & Customer Relation Officer';

    public function handle(): int
    {
        if (!Schema::hasColumn('master_karyawan', 'no_telpon_perusahaan')) {
            $this->error('Kolom no_telpon_perusahaan belum ada. Jalankan migrasi terlebih dahulu.');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Mode dry-run aktif — tidak ada perubahan yang disimpan.');
        }

        $positionIds = MasterKaryawanCompanyPhoneHelper::AUTO_POSITION_IDS;
        $positionNames = MasterKaryawanCompanyPhoneHelper::AUTO_POSITION_NAMES;

        $query = MasterKaryawan::query()
            ->with('jabatan')
            ->where(function ($builder) use ($positionIds, $positionNames) {
                $builder->whereIn('id_jabatan', $positionIds)
                    ->orWhereHas('jabatan', function ($sub) use ($positionNames) {
                        $sub->whereRaw(
                            'LOWER(TRIM(nama_jabatan)) IN (' . implode(',', array_fill(0, count($positionNames), '?')) . ')',
                            $positionNames
                        );
                    });
            });

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $unchanged = 0;

        foreach ($query->cursor() as $karyawan) {
            $processed++;
            $target = MasterKaryawanCompanyPhoneHelper::resolveFromKaryawanRow($karyawan);

            if ($target === null) {
                $skipped++;
                continue;
            }

            $current = trim((string) ($karyawan->no_telpon_perusahaan ?? ''));
            if ($current === $target) {
                $unchanged++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] ID %s (%s): "%s" → "%s"',
                    $karyawan->id,
                    $karyawan->nama_lengkap,
                    $current === '' ? '(kosong)' : $current,
                    $target
                ));
            } else {
                $karyawan->no_telpon_perusahaan = $target;
                $karyawan->save();
            }

            $updated++;
        }

        $this->newLine();
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Diproses', $processed],
                ['Diupdate', $updated],
                ['Sudah sama', $unchanged],
                ['Dilewati (no telpon kosong)', $skipped],
            ]
        );

        if ($dryRun && $updated > 0) {
            $this->warn('Jalankan tanpa --dry-run untuk menyimpan perubahan.');
        } elseif (!$dryRun) {
            $this->info('Selesai. No telpon perusahaan sales/CRO disinkronkan dari no telpon.');
        }

        return 0;
    }
}
