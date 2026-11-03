<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\Greatday\AbsensiAndroid;
use App\Models\RfidCard;
use App\Models\ShiftKaryawan;
use App\Services\Hr\ShiftAbsensiPunchRules;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncGreatdayMobileAbsensiCommand extends Command
{
    protected $signature = 'greatday:sync-mobile-absensi
                            {--date= : Tanggal YYYY-MM-DD (default: hari ini WIB)}
                            {--from= : Awal rentang YYYY-MM-DD}
                            {--to= : Akhir rentang YYYY-MM-DD}
                            {--user= : karyawan_id spesifik}
                            {--dry-run : Hanya tampilkan yang akan diisi, tanpa insert}
                            {--fix-status : Perbaiki kolom status absensi yang sudah ada (jam sama, dari log mobile)}';

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
        $fixStatus = (bool) $this->option('fix-status');
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
        $rangeEnd = Carbon::parse($endDate)->addDay()->toDateString();
        $existing = Absensi::on($connection)
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereBetween('tanggal', [$startDate, $rangeEnd])
            ->get(['id', 'karyawan_id', 'tanggal', 'jam', 'status']);

        /** @var array<string, object> $existingByKey */
        $existingByKey = [];
        foreach ($existing as $row) {
            $key = $this->punchKey($row->karyawan_id, $row->tanggal, $row->jam);
            if ($key !== null) {
                $existingByKey[$key] = $row;
            }
        }

        $rfidByUser = RfidCard::on($connection)
            ->whereIn('userid', $karyawanIds)
            ->where('status', 0)
            ->get(['userid', 'kode_kartu'])
            ->keyBy('userid');

        $shiftRangeStart = Carbon::parse($startDate)->subDay()->toDateString();
        $shiftByKaryawanDate = ShiftKaryawan::on($connection)
            ->select('karyawan_id', 'tanggal', 'shift')
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereBetween('tanggal', [$shiftRangeStart, $endDate])
            ->get()
            ->keyBy(fn ($row) => (int) $row->karyawan_id . '|' . Carbon::parse($row->tanggal)->toDateString());

        $inserted = 0;
        $skipped = 0;
        $statusFixed = 0;

        foreach ($mobileRows as $row) {
            $key = $this->punchKey($row->karyawan_id, $row->tanggal, $row->jam);
            if ($key === null) {
                $skipped++;
                $this->line("  [skip-invalid] karyawan={$row->karyawan_id} tanggal={$row->tanggal} jam={$row->jam}");
                continue;
            }

            $tanggal = Carbon::parse($row->tanggal)->toDateString();
            $jam = $this->normalizeJam($row->jam);
            $shiftCode = $this->resolveShiftForMobilePunch(
                (int) $row->karyawan_id,
                $tanggal,
                $jam,
                $shiftByKaryawanDate
            );
            $status = ShiftAbsensiPunchRules::inferProductionStatus($shiftCode, $jam);
            $context = $this->describePunchContext($tanggal, $jam, $shiftCode, $status);
            $label = "karyawan={$row->karyawan_id} {$tanggal} {$jam} status={$status}{$context}";

            if (isset($existingByKey[$key])) {
                $existingRow = $existingByKey[$key];
                $currentStatus = (string) ($existingRow->status ?? '');
                if ($fixStatus && $currentStatus !== '' && strcasecmp($currentStatus, $status) !== 0) {
                    if ($dryRun) {
                        $this->warn("  [dry-run fix-status] {$label} (absensi id={$existingRow->id}: {$currentStatus} → {$status})");
                    } else {
                        Absensi::on($connection)->where('id', $existingRow->id)->update(['status' => $status]);
                        $this->warn("  [fix-status] {$label} (id={$existingRow->id}: {$currentStatus} → {$status})");
                    }
                    $statusFixed++;
                } elseif ($dryRun) {
                    $this->line("  [skip-exists] {$label}" . ($currentStatus !== '' ? " (absensi status={$currentStatus})" : ''));
                }
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->info("  [dry-run insert] {$label}");
                $inserted++;
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
            $absen->status = $status;
            $absen->save();

            $inserted++;
            $this->info("  [sync insert] {$label}");
        }

        $this->line('');
        if ($dryRun) {
            $this->comment('Catatan SHSECURITY2: pulang shift tercatat di absensi_mobile dengan tanggal keesokan harinya (< 14:00), bukan di tanggal masuk malam.');
        }
        $summary = ($dryRun ? 'Dry-run' : 'Selesai') . ": {$inserted} insert baru, {$skipped} sudah ada/invalid";
        if ($fixStatus || $statusFixed > 0) {
            $summary .= ", {$statusFixed} perbaikan status";
        }
        $this->info($summary . '.');

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

    /**
     * Shift untuk infer status: pagi (<14:00) pada shift malam = pulang shift **hari sebelumnya**.
     *
     * @param  \Illuminate\Support\Collection<string, object>  $shiftByKaryawanDate
     */
    private function resolveShiftForMobilePunch(int $karyawanId, string $tanggal, string $jam, $shiftByKaryawanDate): ?string
    {
        $keyToday = $karyawanId . '|' . $tanggal;
        $shiftToday = optional($shiftByKaryawanDate->get($keyToday))->shift;

        if ($jam < ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE) {
            $prev = Carbon::parse($tanggal)->subDay()->toDateString();
            $shiftPrev = optional($shiftByKaryawanDate->get($karyawanId . '|' . $prev))->shift;
            if ($shiftPrev && strtolower((string) $shiftPrev) === 'shsecurity2') {
                return $shiftPrev;
            }
        }

        return $shiftToday;
    }

    private function describePunchContext(string $tanggal, string $jam, ?string $shiftCode, string $status): string
    {
        $shift = strtolower(trim((string) $shiftCode));
        if ($shift !== 'shsecurity2') {
            return $shiftCode ? " shift={$shiftCode}" : '';
        }

        if ($status === 'Keluar' && $jam < ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE) {
            $shiftDate = Carbon::parse($tanggal)->subDay()->toDateString();

            return " shift={$shiftCode} untuk_pulang_shift={$shiftDate}";
        }

        if ($status === 'Masuk') {
            return " shift={$shiftCode} untuk_masuk_shift={$tanggal}";
        }

        return $shiftCode ? " shift={$shiftCode}" : '';
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
