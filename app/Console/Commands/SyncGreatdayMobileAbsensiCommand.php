<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\Greatday\AbsensiAndroid;
use App\Models\RfidCard;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncGreatdayMobileAbsensiCommand extends Command
{
    protected $signature = 'greatday:sync-mobile-absensi
                            {--date= : Tanggal YYYY-MM-DD (default: hari ini WIB)}
                            {--from= : Awal rentang YYYY-MM-DD}
                            {--to= : Akhir rentang YYYY-MM-DD}
                            {--user= : karyawan_id spesifik}
                            {--dry-run : Hanya tampilkan yang akan diisi, tanpa insert}';

    protected $description = 'Isi ulang tabel absensi dari absensi_mobile yang belum punya baris produksi (RFID sync gagal)';

    public function handle(): int
    {
        [$startDate, $endDate] = $this->resolveDateRange();
        if ($startDate === null) {
            return 1;
        }

        $userId = $this->option('user');
        $userId = $userId !== null && $userId !== '' ? (int) $userId : null;
        $dryRun = (bool) $this->option('dry-run');
        $connection = (new AbsensiAndroid())->getConnectionName() ?: config('database.default', 'mysql');

        $mobileQuery = AbsensiAndroid::query()
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal')
            ->orderBy('jam');

        if ($userId) {
            $mobileQuery->where('karyawan_id', $userId);
        }

        $mobileRows = $mobileQuery->get();
        if ($mobileRows->isEmpty()) {
            $this->warn("Tidak ada log absensi_mobile untuk {$startDate} s/d {$endDate}.");

            return 0;
        }

        $karyawanIds = $mobileRows->pluck('karyawan_id')->unique()->values()->all();
        $existing = Absensi::on($connection)
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get(['karyawan_id', 'tanggal', 'jam']);

        $existingKeys = [];
        foreach ($existing as $row) {
            $key = $this->punchKey($row->karyawan_id, $row->tanggal, $row->jam);
            if ($key !== null) {
                $existingKeys[$key] = true;
            }
        }

        $rfidByUser = RfidCard::on($connection)
            ->whereIn('userid', $karyawanIds)
            ->where('status', 0)
            ->get(['userid', 'kode_kartu'])
            ->keyBy('userid');

        $inserted = 0;
        $skipped = 0;

        foreach ($mobileRows as $row) {
            $key = $this->punchKey($row->karyawan_id, $row->tanggal, $row->jam);
            if ($key === null) {
                $skipped++;
                $this->line("  [skip-invalid] karyawan={$row->karyawan_id} tanggal={$row->tanggal} jam={$row->jam}");
                continue;
            }

            if (isset($existingKeys[$key])) {
                $skipped++;
                continue;
            }

            $tanggal = Carbon::parse($row->tanggal)->toDateString();
            $jam = $this->normalizeJam($row->jam);
            $label = "karyawan={$row->karyawan_id} {$tanggal} {$jam}";

            if ($dryRun) {
                $this->info("  [dry-run] {$label}");
                $inserted++;
                $existingKeys[$key] = true;
                continue;
            }

            $absen = new Absensi();
            $absen->setConnection($connection);
            $absen->karyawan_id = $row->karyawan_id;
            $absen->tanggal = $tanggal;
            $absen->jam = $jam;
            $absen->hari = $this->hariIndonesia($tanggal);
            $absen->kode_mesin = 'GREATDAY';
            $absen->kode_kartu = optional($rfidByUser->get($row->karyawan_id))->kode_kartu;
            $absen->status = strtotime($jam) > strtotime('14:00:00') ? 'Keluar' : 'Masuk';
            $absen->save();

            $existingKeys[$key] = true;
            $inserted++;
            $this->info("  [sync] {$label}");
        }

        $this->line('');
        $this->info(($dryRun ? 'Dry-run' : 'Selesai') . ": {$inserted} baris akan/sudah masuk absensi, {$skipped} dilewati (sudah ada/invalid).");

        return 0;
    }

    /** @return array{0:?string,1:?string} */
    private function resolveDateRange(): array
    {
        $date = trim((string) $this->option('date'));
        $from = trim((string) $this->option('from'));
        $to = trim((string) $this->option('to'));

        if ($from !== '' || $to !== '') {
            if ($from === '' || $to === '') {
                $this->error('Pakai --from dan --to bersama-sama.');

                return [null, null];
            }
            if (!$this->isYmd($from) || !$this->isYmd($to)) {
                $this->error('Format --from/--to harus YYYY-MM-DD.');

                return [null, null];
            }

            return [$from, $to];
        }

        if ($date !== '') {
            if (!$this->isYmd($date)) {
                $this->error('Format --date harus YYYY-MM-DD.');

                return [null, null];
            }

            return [$date, $date];
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();

        return [$today, $today];
    }

    private function isYmd(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function punchKey($karyawanId, $tanggal, $jam): ?string
    {
        $jamKey = $this->normalizeJam($jam);
        if ($jamKey === null || $tanggal === null || $tanggal === '') {
            return null;
        }

        return (int) $karyawanId . '|' . Carbon::parse($tanggal)->toDateString() . '|' . $jamKey;
    }

    private function normalizeJam($jam): ?string
    {
        if ($jam === null || $jam === '') {
            return null;
        }

        $value = str_replace('.', ':', trim((string) $jam));
        $ts = strtotime($value);

        return $ts ? date('H:i:s', $ts) : null;
    }

    private function hariIndonesia(string $tanggal): string
    {
        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        return $days[date('l', strtotime($tanggal))] ?? 'Tidak di ketahui';
    }
}
