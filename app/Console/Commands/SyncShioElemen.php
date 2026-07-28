<?php

namespace App\Console\Commands;

use App\Helpers\ShioElemenHelper;
use App\Models\MasterKaryawan;
use App\Models\Recruitment;
use Illuminate\Console\Command;

class SyncShioElemen extends Command
{
    protected $signature = 'hrd:sync-shio-elemen {--dry-run : Tampilkan perubahan tanpa menyimpan ke database}';

    protected $description = 'Recalculate dan update shio & elemen di tabel recruitment dan master_karyawan berdasarkan tanggal lahir';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Mode dry-run aktif — tidak ada perubahan yang disimpan.');
        }

        $recruitmentStats = $this->syncTable(
            Recruitment::query()->whereNotNull('tanggal_lahir'),
            'recruitment',
            $dryRun
        );

        $karyawanStats = $this->syncTable(
            MasterKaryawan::query()->whereNotNull('tanggal_lahir'),
            'master_karyawan',
            $dryRun
        );

        $this->newLine();
        $this->info('=== Ringkasan ===');
        $this->table(
            ['Tabel', 'Diproses', 'Diupdate', 'Sudah benar', 'Gagal hitung'],
            [
                ['recruitment', ...array_values($recruitmentStats)],
                ['master_karyawan', ...array_values($karyawanStats)],
            ]
        );

        if ($dryRun) {
            $this->warn('Jalankan tanpa --dry-run untuk menyimpan perubahan.');
        } else {
            $this->info('Selesai. Shio & elemen berhasil disinkronkan.');
        }

        return 0;
    }

    private function syncTable($query, string $label, bool $dryRun): array
    {
        $processed = 0;
        $updated = 0;
        $unchanged = 0;
        $failed = 0;

        $this->info("Memproses {$label}...");

        $query->orderBy('id')->chunkById(200, function ($rows) use ($dryRun, &$processed, &$updated, &$unchanged, &$failed) {
            foreach ($rows as $row) {
                $processed++;

                $result = ShioElemenHelper::resolve($row->tanggal_lahir, $row->shio, $row->elemen);
                $newShio = $result['shio'] ?? null;
                $newElemen = $result['elemen'] ?? null;

                if (!$newShio && !$newElemen) {
                    $failed++;
                    continue;
                }

                $shioChanged = $newShio && $row->shio !== $newShio;
                $elemenChanged = $newElemen && $row->elemen !== $newElemen;

                if (!$shioChanged && !$elemenChanged) {
                    $unchanged++;
                    continue;
                }

                if (!$dryRun) {
                    if ($newShio) {
                        $row->shio = $newShio;
                    }
                    if ($newElemen) {
                        $row->elemen = $newElemen;
                    }
                    $row->save();
                }

                $updated++;
            }
        });

        return compact('processed', 'updated', 'unchanged', 'failed');
    }
}
