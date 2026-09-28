<?php

namespace App\Services\Greatday;

use App\Models\Hr\HrSpecialLeaveType;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveRequestValidationService
{
    public const TYPE_ANNUAL = 'Annual Leave';

    public const TYPE_SPECIAL = 'Special Leave';

    public const TYPE_UNPAID = 'Unpaid Leave';

    public const TYPE_PHL = 'Holiday Replacement Leave';

    /** @var LeaveBalanceService */
    private $leaveBalance;

    /** @var OfficeCalendarService */
    private $officeCalendar;

    public function __construct(LeaveBalanceService $leaveBalance, OfficeCalendarService $officeCalendar)
    {
        $this->leaveBalance = $leaveBalance;
        $this->officeCalendar = $officeCalendar;
    }

    /**
     * @return string|null Pesan error (Bahasa Indonesia) atau null jika valid
     */
    public function validateForStore(
        MasterKaryawan $employee,
        string $type,
        ?string $startDate,
        ?string $endDate,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null,
        ?int $specialLeaveTypeId = null,
        bool $hasAttachment = false
    ): ?string {
        if (!$startDate || !$endDate) {
            return 'Tanggal mulai dan tanggal selesai wajib diisi.';
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        if ($end->lt($start)) {
            return 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        switch ($type) {
            case self::TYPE_ANNUAL:
                return $this->validateAnnualLeave($employee, $startDate, $endDate, $excludeHrRequestId, $excludeLegacyId);
            case self::TYPE_SPECIAL:
                return $this->validateSpecialLeave(
                    $employee,
                    $startDate,
                    $endDate,
                    $specialLeaveTypeId,
                    $hasAttachment,
                    $excludeHrRequestId,
                    $excludeLegacyId
                );
            case self::TYPE_UNPAID:
                return $this->validateUnpaidLeave($employee, $startDate, $endDate, $excludeHrRequestId, $excludeLegacyId);
            case self::TYPE_PHL:
                return $this->validatePhlLeave($employee, $startDate, $endDate, $excludeHrRequestId, $excludeLegacyId);
            default:
                return 'Jenis cuti tidak dikenali.';
        }
    }

    private function validateAnnualLeave(
        MasterKaryawan $employee,
        string $startDate,
        string $endDate,
        ?int $excludeHrRequestId,
        ?int $excludeLegacyId
    ): ?string {
        $start = Carbon::parse($startDate)->startOfDay();

        if (!$this->leaveBalance->isEligibleForAnnualLeave($employee)) {
            return 'Anda tidak berhak mengajukan cuti tahunan.';
        }

        $requestedDays = $this->leaveBalance->countWeekdaysBetween($startDate, $endDate);
        if ($requestedDays <= 0) {
            return 'Rentang tanggal tidak memuat hari kerja.';
        }

        $minDaysBefore = (int) config('greatday.leave_annual_min_days_before', 14);
        $earliestStart = Carbon::now()->startOfDay()->addDays($minDaysBefore);
        if ($start->lt($earliestStart)) {
            return "Cuti tahunan harus diajukan minimal H-{$minDaysBefore} hari kalender (mulai {$earliestStart->format('d-m-Y')}).";
        }

        $remaining = $this->leaveBalance->remainingAnnualLeaveDays($employee, $excludeHrRequestId, $excludeLegacyId);
        if ($requestedDays > $remaining) {
            return "Sisa cuti tidak mencukupi (sisa {$remaining} hari kerja, pengajuan {$requestedDays} hari).";
        }

        $holidayAdjacency = $this->validateNotAdjacentToCompanyHoliday($startDate, $endDate);
        if ($holidayAdjacency !== null) {
            return $holidayAdjacency;
        }

        $rollingError = $this->validateRolling30DayLimit(
            $employee,
            $startDate,
            $endDate,
            $excludeHrRequestId,
            $excludeLegacyId,
            self::TYPE_ANNUAL
        );
        if ($rollingError !== null) {
            return $rollingError;
        }

        return null;
    }

    private function validateSpecialLeave(
        MasterKaryawan $employee,
        string $startDate,
        string $endDate,
        ?int $specialLeaveTypeId,
        bool $hasAttachment,
        ?int $excludeHrRequestId,
        ?int $excludeLegacyId
    ): ?string {
        if (!$specialLeaveTypeId) {
            return 'Jenis cuti khusus wajib dipilih.';
        }

        $typeRow = HrSpecialLeaveType::query()
            ->where('id', $specialLeaveTypeId)
            ->where('is_active', true)
            ->first();

        if (!$typeRow) {
            return 'Jenis cuti khusus tidak valid.';
        }

        $unit = $this->normalizeDurationUnit($typeRow->duration_unit ?? 'weekday');
        $requestedDays = $this->leaveBalance->countDaysForSpecialType($startDate, $endDate, $unit);
        if ($requestedDays <= 0) {
            return 'Rentang tanggal tidak valid untuk cuti khusus ini.';
        }

        if ($typeRow->requires_attachment && !$hasAttachment) {
            return 'Cuti khusus ini wajib melampirkan surat/bukti pendukung.';
        }

        $maxUses = $typeRow->max_uses !== null ? (int) $typeRow->max_uses : null;
        if ($maxUses !== null && $maxUses > 0) {
            $stats = $this->leaveBalance->specialLeaveUsageStats(
                $employee,
                (int) $typeRow->id,
                $excludeHrRequestId,
                $excludeLegacyId
            );
            if ($stats['submission_count'] >= $maxUses) {
                return "Kuota pengajuan cuti khusus \"{$typeRow->name}\" sudah habis (maks. {$maxUses}x).";
            }
        }

        $maxDuration = (int) ($typeRow->duration ?? 0);
        if ($unit !== 'open' && $maxDuration > 0 && $requestedDays > $maxDuration) {
            $label = $unit === 'calendar' ? 'hari kalender' : 'hari kerja';

            return "Cuti khusus \"{$typeRow->name}\" maksimal {$maxDuration} {$label} (pengajuan {$requestedDays} {$label}).";
        }

        return null;
    }

    private function validateUnpaidLeave(
        MasterKaryawan $employee,
        string $startDate,
        string $endDate,
        ?int $excludeHrRequestId,
        ?int $excludeLegacyId
    ): ?string {
        $requestedDays = $this->leaveBalance->countWeekdaysBetween($startDate, $endDate);
        if ($requestedDays <= 0) {
            return 'Rentang tanggal tidak memuat hari kerja.';
        }

        if ($this->leaveBalance->isEligibleForAnnualLeave($employee)) {
            $remaining = $this->leaveBalance->remainingAnnualLeaveDays($employee, $excludeHrRequestId, $excludeLegacyId);
            if ($remaining > 0) {
                return "Masih ada sisa cuti tahunan ({$remaining} hari). Gunakan cuti tahunan terlebih dahulu sebelum unpaid leave.";
            }
        }

        return null;
    }

    private function validatePhlLeave(
        MasterKaryawan $employee,
        string $startDate,
        string $endDate,
        ?int $excludeHrRequestId,
        ?int $excludeLegacyId
    ): ?string {
        $requestedDays = $this->leaveBalance->countWeekdaysBetween($startDate, $endDate);
        if ($requestedDays <= 0) {
            return 'Pengganti hari libur harus pada hari kerja.';
        }

        if ($requestedDays > 1) {
            return 'Satu pengajuan pengganti hari libur maksimal 1 hari kerja.';
        }

        $rollingError = $this->validateRolling30DayLimit(
            $employee,
            $startDate,
            $endDate,
            $excludeHrRequestId,
            $excludeLegacyId,
            self::TYPE_PHL
        );
        if ($rollingError !== null) {
            return $rollingError;
        }

        return null;
    }

    private function validateNotAdjacentToCompanyHoliday(string $startDate, string $endDate): ?string
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $bufferFrom = $start->copy()->subDays(3)->format('Y-m-d');
        $bufferTo = $end->copy()->addDays(3)->format('Y-m-d');
        $holidaySet = array_flip($this->officeCalendar->holidayDatesBetween($bufferFrom, $bufferTo));

        foreach (CarbonPeriod::create($start, $end) as $day) {
            if (!$day->isWeekday()) {
                continue;
            }
            $prev = $day->copy()->subDay()->format('Y-m-d');
            $next = $day->copy()->addDay()->format('Y-m-d');
            if (isset($holidaySet[$prev]) || isset($holidaySet[$next])) {
                return 'Cuti tahunan tidak boleh diambil sebelum atau sesudah tanggal merah perusahaan/libur nasional (tanggal ' . $day->format('d-m-Y') . ').';
            }
        }

        return null;
    }

    private function validateRolling30DayLimit(
        MasterKaryawan $employee,
        string $startDate,
        string $endDate,
        ?int $excludeHrRequestId,
        ?int $excludeLegacyId,
        string $forType
    ): ?string {
        $windowDays = (int) config('greatday.leave_annual_rolling_window_days', 30);
        $maxAnnual = (int) config('greatday.leave_annual_max_days_per_window', 3);
        $maxPhl = (int) config('greatday.leave_phl_max_weekdays_per_window', 1);
        $maxCombined = (int) config('greatday.leave_combined_max_weekdays_per_window', 4);

        $existingAnnual = $this->leaveBalance->annualLeaveWeekdayDates($employee, $excludeHrRequestId, $excludeLegacyId);
        $existingPhl = $this->leaveBalance->phlLeaveWeekdayDates($employee, $excludeHrRequestId, $excludeLegacyId);

        $newAnnual = $forType === self::TYPE_ANNUAL
            ? $this->leaveBalance->expandWeekdayDates($startDate, $endDate)
            : [];
        $newPhl = $forType === self::TYPE_PHL
            ? $this->leaveBalance->expandWeekdayDates($startDate, $endDate)
            : [];

        $anchors = array_unique(array_merge($newAnnual, $newPhl));
        foreach ($anchors as $anchorYmd) {
            $anchor = Carbon::parse($anchorYmd)->startOfDay();
            $windowStart = $anchor->copy()->subDays($windowDays - 1);
            $windowEnd = $anchor->copy();

            $annualDates = array_values(array_unique(array_merge($existingAnnual, $newAnnual)));
            $phlDates = array_values(array_unique(array_merge($existingPhl, $newPhl)));

            $annualCount = $this->countDatesInWindow($annualDates, $windowStart, $windowEnd);
            $phlCount = $this->countDatesInWindow($phlDates, $windowStart, $windowEnd);
            $combined = $annualCount + $phlCount;

            if ($forType === self::TYPE_ANNUAL && $annualCount > $maxAnnual) {
                return "Maksimal {$maxAnnual} hari kerja cuti tahunan dalam {$windowDays} hari kalender (terlampaui per {$anchor->format('d-m-Y')}).";
            }
            if ($forType === self::TYPE_PHL && $phlCount > $maxPhl) {
                return "Maksimal {$maxPhl} hari kerja pengganti hari libur dalam {$windowDays} hari kalender (terlampaui per {$anchor->format('d-m-Y')}).";
            }
            if ($combined > $maxCombined) {
                return "Gabungan cuti tahunan dan pengganti hari libur maksimal {$maxCombined} hari kerja dalam {$windowDays} hari kalender (terlampaui per {$anchor->format('d-m-Y')}).";
            }
        }

        return null;
    }

    /**
     * @param list<string> $dates Y-m-d
     */
    private function countDatesInWindow(array $dates, Carbon $windowStart, Carbon $windowEnd): int
    {
        $count = 0;
        foreach ($dates as $d) {
            $c = Carbon::parse($d)->startOfDay();
            if ($c->gte($windowStart) && $c->lte($windowEnd)) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeDurationUnit(?string $unit): string
    {
        $u = strtolower(trim((string) $unit));
        if ($u === 'calendar') {
            return 'calendar';
        }
        if ($u === 'open') {
            return 'open';
        }

        return 'weekday';
    }
}
