<?php

namespace App\Console\Commands;

use App\Models\MasterKaryawan;
use App\Models\RekapMasukKerja;
use App\Services\Payroll\PayrollWorkingDaysService;
use Illuminate\Console\Command;

class BackfillRekapHariKerjaCommand extends Command
{
    protected $signature = 'absensi:backfill-hari-kerja
                            {--bulan= : Batasi ke periode YYYY-MM}
                            {--dry-run : Hanya tampilkan, tanpa update}';

    protected $description = 'Isi rekap_masuk_kerja.hari_kerja yang masih NULL dari kalender/jabatan (setelah migrasi kolom hari_kerja)';

    public function handle(): int
    {
        $bulan = trim((string) $this->option('bulan'));
        $dryRun = (bool) $this->option('dry-run');

        $query = RekapMasukKerja::query()
            ->where('is_active', true)
            ->whereNull('hari_kerja');

        if ($bulan !== '') {
            if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
                $this->error('Format --bulan harus YYYY-MM.');

                return 1;
            }
            $query->where('bulan', $bulan);
        }

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->info('Tidak ada rekap aktif dengan hari_kerja NULL.');

            return 0;
        }

        $updated = 0;
        foreach ($rows as $rekap) {
            $karyawanId = (int) $rekap->karyawan_id;
            $bulanYm = (string) $rekap->bulan;
            $meta = MasterKaryawan::select('id_jabatan', 'nik_karyawan', 'nama_lengkap')->find($karyawanId);
            if (!$meta) {
                $this->warn("Rekap #{$rekap->id}: karyawan {$karyawanId} tidak ditemukan.");

                continue;
            }
            $expected = PayrollWorkingDaysService::expectedHariKerjaForKaryawan(
                $karyawanId,
                $meta->id_jabatan !== null ? (int) $meta->id_jabatan : null,
                $bulanYm
            );
            $this->line(sprintf(
                'Rekap #%d %s %s — %s → hari_kerja %d',
                $rekap->id,
                $meta->nik_karyawan,
                $meta->nama_lengkap,
                $bulanYm,
                $expected
            ));
            if (!$dryRun) {
                $rekap->hari_kerja = $expected;
                $rekap->save();
            }
            $updated++;
        }

        $this->info(($dryRun ? 'Akan update' : 'Updated') . " {$updated} rekap.");

        return 0;
    }
}
