<?php

namespace App\Services\Greatday;

use App\Models\Absensi;
use App\Models\MasterKaryawan;
use App\Models\ShiftKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Deteksi karyawan yang belum absen masuk/pulang sesuai shift (log-only / pra-notifikasi).
 */
class AttendanceReminderDetectionService
{
    public const DEFAULT_TIME_IN = '08:00:00';

    public const DEFAULT_TIME_OUT = '17:00:00';

    public const SLOT_MORNING = 'morning';

    public const SLOT_EVENING = 'evening';

    /** Grade yang tidak menerima reminder absensi. */
    private const EXEMPT_GRADES = ['MANAGER', 'DIRECTOR','SENIOR MANAGER',NULL];

    /** @var string */
    private $connection;

    public function __construct()
    {
        $this->connection = (string) config('greatday.produksi_connection', config('database.default', 'mysql'));
    }

    /**
     * @param  list<int>|null  $karyawanIds
     * @return array{date: string, slot: string, slot_time: string, scanned: int, skipped: int, reminders: list<array<string, mixed>>}
     */
    public function detect(Carbon $date, string $slot, ?array $karyawanIds = null): array
    {
        $slot = $this->normalizeSlot($slot);
        $date = $date->copy()->timezone('Asia/Jakarta')->startOfDay();
        $now = Carbon::now('Asia/Jakarta');
        $slotTime = $now->hour * 3600 + $now->minute * 60 + $now->second;

        $employees = $this->loadEmployees($karyawanIds);
        $ids = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();

        $prev = $date->copy()->subDay()->toDateString();
        $today = $date->toDateString();
        $next = $date->copy()->addDay()->toDateString();

        $shifts = $this->loadShifts($ids, [$prev, $today, $next]);
        $attendance = $this->loadAttendanceFlags($ids, [$prev, $today, $next]);
        $excuseCalendar = LeaveAlpaExcuseCalendar::forProduksiAttendanceReminder($ids, $date->copy()->subDay(), $date);

        $reminders = [];
        $skipped = 0;

        foreach ($employees as $employee) {
            $evaluation = $this->evaluateEmployee(
                $employee,
                $date,
                $slot,
                $slotTime,
                $shifts,
                $attendance,
                $excuseCalendar
            );

            if ($evaluation === null) {
                $skipped++;

                continue;
            }

            $reminders[] = $evaluation;
        }

        return [
            'date' => $today,
            'slot' => $slot,
            'slot_time' => $now->format('H:i:s'),
            'scanned' => $employees->count(),
            'skipped' => $skipped,
            'reminders' => $reminders,
        ];
    }

    /**
     * @param  list<int>|null  $karyawanIds
     */
    private function loadEmployees(?array $karyawanIds): Collection
    {
        $query = MasterKaryawan::on($this->connection)
            ->where('is_active', 1)
            ->orderBy('nama_lengkap');

        if ($karyawanIds !== null && $karyawanIds !== []) {
            $query->whereIn('id', $karyawanIds);
        }

        return $query
            ->get(['id', 'nama_lengkap', 'nik_karyawan', 'grade'])
            ->filter(fn (MasterKaryawan $employee) => !$this->isExemptGrade($employee))
            ->values();
    }

    private function isExemptGrade(MasterKaryawan $employee): bool
    {
        $grade = strtoupper(trim(str_replace('_', ' ', (string) ($employee->grade ?? ''))));

        return in_array($grade, self::EXEMPT_GRADES, true);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $dates
     */
    private function loadShifts(array $ids, array $dates): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return ShiftKaryawan::on($this->connection)
            ->whereIn('karyawan_id', $ids)
            ->whereIn('tanggal', $dates)
            ->get(['karyawan_id', 'tanggal', 'shift', 'time_in', 'time_out'])
            ->groupBy(fn ($row) => (int) $row->karyawan_id . '|' . Carbon::parse($row->tanggal)->toDateString());
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $dates
     * @return Collection<string, array{masuk: bool, keluar: bool}>
     */
    private function loadAttendanceFlags(array $ids, array $dates): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $rows = Absensi::on($this->connection)
            ->whereIn('karyawan_id', $ids)
            ->whereIn('tanggal', $dates)
            ->whereIn('status', ['Masuk', 'Keluar'])
            ->get(['karyawan_id', 'tanggal', 'status']);

        $flags = [];
        foreach ($rows as $row) {
            $key = (int) $row->karyawan_id . '|' . Carbon::parse($row->tanggal)->toDateString();
            if (!isset($flags[$key])) {
                $flags[$key] = ['masuk' => false, 'keluar' => false];
            }
            $status = trim((string) $row->status);
            if ($status === 'Masuk') {
                $flags[$key]['masuk'] = true;
            } elseif ($status === 'Keluar') {
                $flags[$key]['keluar'] = true;
            }
        }

        return collect($flags);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evaluateEmployee(
        MasterKaryawan $employee,
        Carbon $date,
        string $slot,
        int $slotTime,
        Collection $shifts,
        Collection $attendance,
        LeaveAlpaExcuseCalendar $excuseCalendar
    ): ?array {
        $karyawanId = (int) $employee->id;
        $today = $date->toDateString();
        $prev = $date->copy()->subDay()->toDateString();
        $next = $date->copy()->addDay()->toDateString();

        $shiftToday = $this->shiftRow($shifts, $karyawanId, $today);
        $shiftPrev = $this->shiftRow($shifts, $karyawanId, $prev);
        if ($excuseCalendar->isExcused($karyawanId, $prev)) {
            $shiftPrev = null;
        }

        if ($slot === self::SLOT_MORNING) {
            $reminder = $this->evaluateMorningSlot(
                $employee,
                $today,
                $prev,
                $slotTime,
                $shiftToday,
                $shiftPrev,
                $attendance
            );
        } else {
            $reminder = $this->evaluateEveningSlot(
                $employee,
                $today,
                $next,
                $slotTime,
                $shiftToday,
                $attendance
            );
        }

        if ($reminder === null) {
            return null;
        }
        $scheduledAt = Carbon::parse($reminder['shift_date'], 'Asia/Jakarta')->startOfDay()
            ->addSeconds($this->toSeconds($reminder['reminder_type'] === 'missing_masuk'
                ? $reminder['time_in'] : $reminder['time_out']) ?? 0);
        if ($reminder['reminder_type'] === 'missing_pulang'
            && $this->isOvernightShift($reminder['shift'], $reminder['time_in'], $reminder['time_out'])) {
            $scheduledAt->addDay();
        }
        if ($excuseCalendar->isReminderExcused($karyawanId, $reminder['shift_date'],
            $reminder['reminder_type'], $scheduledAt, $date->copy()->addSeconds($slotTime))) {
            return null;
        }

        return $reminder;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evaluateMorningSlot(
        MasterKaryawan $employee,
        string $today,
        string $prev,
        int $slotTime,
        ?object $shiftToday,
        ?object $shiftPrev,
        Collection $attendance
    ): ?array {
        $karyawanId = (int) $employee->id;

        if ($shiftPrev && $this->isOffShift($shiftPrev->shift)) {
            $shiftPrev = null;
        }
        if ($shiftPrev) {
            $prevSchedule = $this->resolveSchedule($shiftPrev);
            if ($this->isOvernightShift($prevSchedule['shift'], $prevSchedule['time_in'], $prevSchedule['time_out'])) {
                $outSeconds = $this->toSeconds($prevSchedule['time_out']) ?? $this->toSeconds(self::DEFAULT_TIME_OUT);
                if ($slotTime >= $outSeconds && !$this->hasKeluar($attendance, $karyawanId, $today)) {
                    return $this->reminderRow(
                        $employee,
                        'missing_pulang',
                        $prev,
                        $prevSchedule,
                        'Shift malam/lintas hari kemarin; belum absen Keluar pagi ini'
                    );
                }
            }
        }

        if (($shiftToday && $this->isOffShift($shiftToday->shift))
            || !$this->shouldExpectWorkToday($today, $shiftToday)) {
            return null;
        }

        $schedule = $this->resolveSchedule($shiftToday);
        $inSeconds = $this->toSeconds($schedule['time_in']) ?? $this->toSeconds(self::DEFAULT_TIME_IN);
        if ($slotTime < $inSeconds) {
            return null;
        }

        if (!$this->hasMasuk($attendance, $karyawanId, $today)) {
            return $this->reminderRow(
                $employee,
                'missing_masuk',
                $today,
                $schedule,
                'Shift normal hari ini; belum absen Masuk'
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function evaluateEveningSlot(
        MasterKaryawan $employee,
        string $today,
        string $next,
        int $slotTime,
        ?object $shiftToday,
        Collection $attendance
    ): ?array {
        $karyawanId = (int) $employee->id;

        if ($shiftToday && $this->isOffShift($shiftToday->shift)) {
            return null;
        }

        if (!$this->shouldExpectWorkToday($today, $shiftToday)) {
            return null;
        }

        $schedule = $this->resolveSchedule($shiftToday);
        $overnight = $this->isOvernightShift($schedule['shift'], $schedule['time_in'], $schedule['time_out']);

        if ($overnight) {
            $inSeconds = $this->toSeconds($schedule['time_in']) ?? $this->toSeconds('20:00:00');
            if ($slotTime < $inSeconds) {
                return null;
            }
            if (!$this->hasMasuk($attendance, $karyawanId, $today)) {
                return $this->reminderRow(
                    $employee,
                    'missing_masuk',
                    $today,
                    $schedule,
                    'Shift malam/lintas hari mulai malam ini; belum absen Masuk'
                );
            }

            return null;
        }

        $outSeconds = $this->toSeconds($schedule['time_out']) ?? $this->toSeconds(self::DEFAULT_TIME_OUT);
        if ($slotTime < $outSeconds) {
            return null;
        }

        if (!$this->hasKeluar($attendance, $karyawanId, $today)) {
            return $this->reminderRow(
                $employee,
                'missing_pulang',
                $today,
                $schedule,
                'Shift normal hari ini; belum absen Keluar'
            );
        }

        return null;
    }

    private function shouldExpectWorkToday(string $today, ?object $shiftRow): bool
    {
        if ($shiftRow) {
            return true;
        }

        $day = Carbon::parse($today, 'Asia/Jakarta');
        if ($day->isWeekend()) {
            return false;
        }

        return true;
    }

    /**
     * @return array{shift: string, time_in: string, time_out: string, source: string}
     */
    private function resolveSchedule(?object $shiftRow): array
    {
        if (!$shiftRow) {
            return [
                'shift' => 'DEFAULT',
                'time_in' => self::DEFAULT_TIME_IN,
                'time_out' => self::DEFAULT_TIME_OUT,
                'source' => 'default_08_17',
            ];
        }

        $timeIn = $this->normalizeClock($shiftRow->time_in) ?: self::DEFAULT_TIME_IN;
        $timeOut = $this->normalizeClock($shiftRow->time_out) ?: self::DEFAULT_TIME_OUT;

        return [
            'shift' => strtoupper(trim((string) $shiftRow->shift)),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'source' => 'shift_karyawan',
        ];
    }

    private function isOffShift($shift): bool
    {
        return strtoupper(trim((string) $shift)) === 'OFF';
    }

    private function isOvernightShift(string $shiftName, string $timeIn, string $timeOut): bool
    {
        $name = strtoupper(trim($shiftName));
        if (in_array($name, ['24JAM', 'SHSECURITY2'], true)) {
            return true;
        }

        $in = $this->toSeconds($timeIn);
        $out = $this->toSeconds($timeOut);
        if ($in === null || $out === null) {
            return false;
        }

        return $out <= $in;
    }

    private function hasMasuk(Collection $attendance, int $karyawanId, string $date): bool
    {
        $key = $karyawanId . '|' . $date;

        return (bool) ($attendance->get($key)['masuk'] ?? false);
    }

    private function hasKeluar(Collection $attendance, int $karyawanId, string $date): bool
    {
        $key = $karyawanId . '|' . $date;

        return (bool) ($attendance->get($key)['keluar'] ?? false);
    }

    private function shiftRow(Collection $shifts, int $karyawanId, string $date): ?object
    {
        $group = $shifts->get($karyawanId . '|' . $date);

        return $group ? $group->first() : null;
    }

    /**
     * @param  array{shift: string, time_in: string, time_out: string, source: string}  $schedule
     * @return array<string, mixed>
     */
    private function reminderRow(
        MasterKaryawan $employee,
        string $type,
        string $shiftDate,
        array $schedule,
        string $reason
    ): array {
        return [
            'karyawan_id' => (int) $employee->id,
            'nama' => (string) $employee->nama_lengkap,
            'nik' => (string) ($employee->nik_karyawan ?? ''),
            'reminder_type' => $type,
            'shift_date' => $shiftDate,
            'shift' => $schedule['shift'],
            'time_in' => $schedule['time_in'],
            'time_out' => $schedule['time_out'],
            'schedule_source' => $schedule['source'],
            'reason' => $reason,
        ];
    }

    private function normalizeSlot(string $slot): string
    {
        $slot = strtolower(trim($slot));
        if ($slot === self::SLOT_EVENING || $slot === '21' || $slot === '2100') {
            return self::SLOT_EVENING;
        }
        if ($slot === 'both' || $slot === 'all') {
            return 'both';
        }

        return self::SLOT_MORNING;
    }

    private function normalizeClock($value): ?string
    {
        $seconds = $this->toSeconds($value);

        return $seconds === null ? null : $this->formatClock($seconds);
    }

    private function toSeconds($value): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '00:00:00') {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $match)) {
            return null;
        }

        return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (int) ($match[3] ?? 0);
    }

    private function formatClock(int $seconds): string
    {
        $normalized = $seconds % 86400;
        if ($normalized < 0) {
            $normalized += 86400;
        }

        return sprintf('%02d:%02d:%02d', intdiv($normalized, 3600), intdiv($normalized % 3600, 60), $normalized % 60);
    }
}
