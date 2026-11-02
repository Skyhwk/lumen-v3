<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;
use App\Models\ShiftKaryawan;
use App\Support\Greatday\KaryawanPresentation;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Hitung jumlah hari kerja dalam periode kalender bulan.
 *
 * - Security: mengikuti jadwal shift saja (abaikan kalender nasional/perusahaan).
 * - Sampler, Office Boy, Office Girl: Senin–Sabtu + kalender nasional/perusahaan.
 * - Lainnya: Senin–Jumat + kalender nasional/perusahaan.
 */
class EmployeeWorkingDaysService
{
    public const PROFILE_SECURITY = 'security';

    public const PROFILE_SATURDAY_WEEK = 'saturday_week';

    public const PROFILE_WEEKDAY = 'weekday';

    private OfficeCalendarService $officeCalendar;

    public function __construct(OfficeCalendarService $officeCalendar)
    {
        $this->officeCalendar = $officeCalendar;
    }

    public function resolveProfile(?string $jabatanLabel): string
    {
        $label = strtolower(trim((string) $jabatanLabel));
        if ($label === '') {
            return self::PROFILE_WEEKDAY;
        }

        if (str_contains($label, 'security')) {
            return self::PROFILE_SECURITY;
        }

        foreach (['sampler', 'office boy', 'office girl'] as $fragment) {
            if (str_contains($label, $fragment)) {
                return self::PROFILE_SATURDAY_WEEK;
            }
        }

        return self::PROFILE_WEEKDAY;
    }

    public function profileForEmployee(MasterKaryawan $employee): string
    {
        return $this->resolveProfile(KaryawanPresentation::jabatanLabel($employee));
    }

    public function countInMonth(MasterKaryawan $employee, int $year, int $month, ?string $dbConnection = null): int
    {
        return $this->monthSummaryForEmployee($employee, $year, $month, $dbConnection)['working_days'];
    }

    /**
     * @return array{
     *   working_days: int,
     *   work_schedule_profile: string,
     *   security_shift_incomplete: bool
     * }
     */
    public function monthSummaryForEmployee(
        MasterKaryawan $employee,
        int $year,
        int $month,
        ?string $dbConnection = null
    ): array {
        $year = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $profile = $this->profileForEmployee($employee);

        if ($profile === self::PROFILE_SECURITY) {
            $security = $this->summarizeSecurityShiftDays($employee, $start, $end, $dbConnection);

            return [
                'working_days' => $security['working_days'],
                'work_schedule_profile' => self::PROFILE_SECURITY,
                'security_shift_incomplete' => $security['shift_incomplete'],
            ];
        }

        return [
            'working_days' => $this->countCalendarBasedDays($profile, $start, $end),
            'work_schedule_profile' => $profile,
            'security_shift_incomplete' => false,
        ];
    }

    public function isSecurityShiftConfiguredForDate(
        MasterKaryawan $employee,
        string $ymd,
        ?string $dbConnection = null
    ): bool {
        if ($this->profileForEmployee($employee) !== self::PROFILE_SECURITY) {
            return true;
        }

        $shiftQuery = ($dbConnection !== null && $dbConnection !== '')
            ? ShiftKaryawan::on($dbConnection)
            : ShiftKaryawan::query();

        return $shiftQuery
            ->where('karyawan_id', $employee->id)
            ->whereDate('tanggal', $ymd)
            ->exists();
    }

    /**
     * @return array{holiday_set: array<string, true>, additional_working_set: array<string, true>}
     */
    public function calendarContextForRange(Carbon $start, Carbon $end): array
    {
        $holidaySet = [];
        $additionalSet = [];

        for ($year = (int) $start->year; $year <= (int) $end->year; $year++) {
            $payload = $this->officeCalendar->forYear($year);
            foreach ($payload['calendar']['holiday_dates'] ?? [] as $date) {
                if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                    $holidaySet[$date] = true;
                }
            }
            foreach ($payload['calendar']['additional_working_dates'] ?? [] as $date) {
                if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                    $additionalSet[$date] = true;
                }
            }
        }

        return [
            'holiday_set' => $holidaySet,
            'additional_working_set' => $additionalSet,
        ];
    }

    public function isCalendarWorkingDay(
        string $ymd,
        string $profile,
        array $holidaySet,
        array $additionalWorkingSet
    ): bool {
        if (isset($additionalWorkingSet[$ymd])) {
            return true;
        }

        try {
            $dayOfWeek = (int) Carbon::parse($ymd)->dayOfWeekIso;
        } catch (\Throwable $e) {
            return false;
        }

        if ($dayOfWeek === 7) {
            return false;
        }

        if ($profile === self::PROFILE_WEEKDAY && $dayOfWeek === 6) {
            return false;
        }

        if (isset($holidaySet[$ymd])) {
            return false;
        }

        return true;
    }

    private function countCalendarBasedDays(string $profile, Carbon $start, Carbon $end): int
    {
        $ctx = $this->calendarContextForRange($start, $end);
        $count = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $ymd = $day->format('Y-m-d');
            if ($this->isCalendarWorkingDay(
                $ymd,
                $profile,
                $ctx['holiday_set'],
                $ctx['additional_working_set']
            )) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{working_days: int, shift_incomplete: bool}
     */
    private function summarizeSecurityShiftDays(
        MasterKaryawan $employee,
        Carbon $start,
        Carbon $end,
        ?string $dbConnection
    ): array {
        $shiftQuery = ($dbConnection !== null && $dbConnection !== '')
            ? ShiftKaryawan::on($dbConnection)
            : ShiftKaryawan::query();

        $shiftsByDate = $shiftQuery
            ->where('karyawan_id', $employee->id)
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->tanggal)->format('Y-m-d'));

        $count = 0;
        $shiftIncomplete = false;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $ymd = $day->format('Y-m-d');
            $shiftRow = $shiftsByDate->get($ymd);
            if ($shiftRow === null) {
                $shiftIncomplete = true;
                $count++;

                continue;
            }

            if ($this->isNonWorkingShift((string) ($shiftRow->shift ?? ''))) {
                continue;
            }

            $count++;
        }

        return [
            'working_days' => $count,
            'shift_incomplete' => $shiftIncomplete,
        ];
    }

    private function isNonWorkingShift(string $shiftName): bool
    {
        $normalized = strtolower(trim($shiftName));

        return in_array($normalized, ['off', 'libur'], true);
    }
}
