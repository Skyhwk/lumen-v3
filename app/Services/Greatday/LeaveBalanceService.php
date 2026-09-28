<?php

namespace App\Services\Greatday;

use App\Models\Greatday\LeaveRequest;
use App\Models\Hr\HrLeaveBalancePeriod;
use App\Models\Hr\HrRequest;
use App\Models\Hr\HrSpecialLeaveType;
use App\Models\MasterKaryawan;
use App\Services\Greatday\LeaveAlpaAttendanceScope;
use App\Services\Hr\HrLeaveBalanceLedgerService;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class LeaveBalanceService
{
    public const FULL_ANNUAL_QUOTA = 12;

    /** @var list<string> */
    private const ELIGIBLE_STATUS_KEYS = [
        'contract',
        'permanent',
        'khusus',
    ];

    public function leaveYearBounds(MasterKaryawan $employee, ?Carbon $asOf = null): array
    {
        $join = $this->joinDate($employee) ?? ($asOf ?? Carbon::now())->copy()->startOfYear();
        $today = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $currentYear = $today->year;

        $anniversaryThisYear = $join->copy()->year($currentYear)->startOfDay();

        if ($today->lt($anniversaryThisYear)) {
            $from = $join->copy()->year($currentYear - 1)->startOfDay();
            $to = $anniversaryThisYear->copy()->subSecond();
        } else {
            $from = $anniversaryThisYear;
            $to = $join->copy()->year($currentYear + 1)->endOfDay();
        }

        return [$from, $to];
    }

    public function isEligibleForAnnualLeave(MasterKaryawan $employee): bool
    {
        $key = $this->normalizeStatusKey($employee->status_karyawan ?? '');

        return in_array($key, self::ELIGIBLE_STATUS_KEYS, true);
    }

    /**
     * Kuota otomatis: Permanent/Khusus = 12 (periode aktif).
     * Kontrak: pro-rata bulan penuh masa kerja di periode pertama (<12 bln sebelum period_start), else 12.
     */
    public function computeQuotaDays(MasterKaryawan $employee, Carbon $periodStart, Carbon $periodEnd, ?Carbon $asOf = null): int
    {
        if (!$this->isEligibleForAnnualLeave($employee)) {
            return 0;
        }

        $join = $this->joinDate($employee);
        if (!$join) {
            return 0;
        }

        $asOf = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $status = $this->normalizeStatusKey($employee->status_karyawan ?? '');

        if (in_array($status, ['permanent', 'khusus'], true)) {
            return self::FULL_ANNUAL_QUOTA;
        }

        if ($join->copy()->addMonthsNoOverflow(12)->lte($periodStart)) {
            return self::FULL_ANNUAL_QUOTA;
        }

        $months = $this->completeMonthsBetween($join, $asOf->min($periodEnd->copy()->startOfDay()));
        $months = max(0, min(12, $months));

        return min(self::FULL_ANNUAL_QUOTA, $months);
    }

    public function ensureActivePeriod(MasterKaryawan $employee, ?Carbon $asOf = null): HrLeaveBalancePeriod
    {
        [$from, $to] = $this->leaveYearBounds($employee, $asOf);
        $periodStart = $from->copy()->startOfDay()->toDateString();
        $periodEnd = $to->copy()->startOfDay()->toDateString();

        $existing = HrLeaveBalancePeriod::query()
            ->where('karyawan_id', $employee->id)
            ->where('period_start', $periodStart)
            ->first();

        if ($existing) {
            HrLeaveBalancePeriod::query()
                ->where('karyawan_id', $employee->id)
                ->where('id', '!=', $existing->id)
                ->update(['is_active' => false]);

            if (!$existing->is_active) {
                $existing->is_active = true;
                $existing->save();
            }

            if ($existing->opening_source === null) {
                $existing->quota_days = $this->computeQuotaDays($employee, $from, $to->copy()->startOfDay(), $asOf);
                $existing->save();
            }

            return $existing->fresh();
        }

        HrLeaveBalancePeriod::query()
            ->where('karyawan_id', $employee->id)
            ->update(['is_active' => false]);

        return HrLeaveBalancePeriod::create([
            'karyawan_id' => $employee->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'quota_days' => $this->computeQuotaDays($employee, $from, $to->copy()->startOfDay(), $asOf),
            'opening_used_days' => 0,
            'is_active' => true,
        ]);
    }

    public function summary(MasterKaryawan $employee): array
    {
        $period = $this->ensureActivePeriod($employee);
        [$from, $to] = $this->leaveYearBounds($employee);

        $openingUsed = (int) $period->opening_used_days;
        $systemUsed = $this->systemUsedDays($employee, $period);
        $pendingUsed = $this->pendingAnnualLeaveWeekdays($employee, $period);
        $ledger = app(HrLeaveBalanceLedgerService::class);
        $ledgerTotals = $ledger->totalsForSummary($employee, $period);
        $ledgerNet = $ledgerTotals['ledger_net'];
        $alpaDays = $ledgerTotals['alpa_days'];
        $quota = (int) $period->quota_days;
        $totalUsed = $openingUsed + $systemUsed + $pendingUsed + $ledgerNet;
        $remaining = max(0, $quota - $totalUsed);

        return [
            'eligible' => $this->isEligibleForAnnualLeave($employee),
            'status_karyawan' => $employee->status_karyawan,
            'period_start' => $this->formatDateYmd($period->period_start),
            'period_end' => $this->formatDateYmd($period->period_end),
            'quota_days' => $quota,
            'opening_used_days' => $openingUsed,
            'system_used_days' => $systemUsed,
            'pending_used_days' => $pendingUsed,
            'ledger_used_days' => $ledgerTotals['ledger_used_days'],
            'alpa_days' => $alpaDays,
            'ledger_net_days' => $ledgerNet,
            'leave_alpa_exempt' => LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee),
            'used_days' => $totalUsed,
            'remaining_days' => $remaining,
            'sisa_cuti' => $remaining,
            'jumlah_cuti' => $totalUsed,
            'reset_label' => $period->notes ?: $this->defaultResetLabel($period),
            'has_opening_balance' => $openingUsed > 0,
            'cutover_at' => config('greatday.leave_balance_cutover_at'),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function usageLedger(MasterKaryawan $employee, int $page = 1, int $perPage = 20): array
    {
        $period = $this->ensureActivePeriod($employee);
        $items = [];

        if ((int) $period->opening_used_days > 0) {
            $items[] = [
                'kind' => 'opening_balance',
                'title' => 'Cuti terpakai sebelum pencatatan sistem',
                'days' => (int) $period->opening_used_days,
                'date_label' => null,
                'status' => 'recorded',
                'request_id' => null,
            ];
        }

        foreach ($this->pendingAnnualLeaveRows($employee, $period) as $row) {
            $days = $this->countWeekdays($row['start_date'] ?? null, $row['end_date'] ?? null);
            if ($days <= 0) {
                continue;
            }
            $items[] = [
                'kind' => 'leave_request',
                'title' => 'Cuti tahunan (menunggu persetujuan)',
                'days' => $days,
                'date_label' => $this->formatDateRange($row['start_date'] ?? null, $row['end_date'] ?? null),
                'status' => WorkflowStatus::PENDING,
                'request_id' => $row['id'] ?? null,
                'no_document' => null,
            ];
        }

        $requests = $this->approvedAnnualLeaveRows($employee, $period);
        foreach ($requests as $row) {
            $days = $this->countWeekdays($row['start_date'] ?? null, $row['end_date'] ?? null);
            if ($days <= 0) {
                continue;
            }
            $items[] = [
                'kind' => 'leave_request',
                'title' => $row['title'] ?? 'Cuti tahunan',
                'days' => $days,
                'date_label' => $this->formatDateRange($row['start_date'] ?? null, $row['end_date'] ?? null),
                'status' => $row['status'] ?? '',
                'request_id' => $row['id'] ?? null,
                'no_document' => $row['no_document'] ?? null,
            ];
        }

        $skipAlpaInLedger = LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee);
        foreach (app(HrLeaveBalanceLedgerService::class)->ledgerRowsForPeriod($period) as $ledgerRow) {
            if ($skipAlpaInLedger && $ledgerRow['entry_type'] === 'alpa') {
                continue;
            }
            $delta = (int) $ledgerRow['days_delta'];
            $kind = $ledgerRow['entry_type'] === 'alpa' ? 'ledger_alpa' : 'ledger_adjustment';
            $title = $ledgerRow['entry_type'] === 'alpa' ? 'Alpa (potong cuti)' : 'Koreksi HRD';
            $items[] = [
                'kind' => $kind,
                'title' => $title,
                'days' => abs($delta),
                'days_signed' => $delta,
                'date_label' => $ledgerRow['reference_date'],
                'status' => $ledgerRow['notes'],
                'ledger_id' => $ledgerRow['id'],
            ];
        }

        usort($items, function ($a, $b) {
            $ka = $a['kind'] === 'opening_balance' ? '0000' : ($a['date_label'] ?? '9999');
            $kb = $b['kind'] === 'opening_balance' ? '0000' : ($b['date_label'] ?? '9999');

            return strcmp($kb, $ka);
        });

        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);
        $hasMore = count($items) > $offset + $perPage;

        return ['items' => $slice, 'has_more' => $hasMore];
    }

    public function remainingAnnualLeaveDays(
        MasterKaryawan $employee,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null
    ): int {
        $period = $this->ensureActivePeriod($employee);
        $quota = (int) $period->quota_days;
        $ledgerTotals = app(HrLeaveBalanceLedgerService::class)->totalsForSummary($employee, $period);
        $ledgerNet = $ledgerTotals['ledger_net'];
        $used = (int) $period->opening_used_days
            + $this->systemUsedDays($employee, $period)
            + $this->pendingAnnualLeaveWeekdays($employee, $period, $excludeHrRequestId, $excludeLegacyId)
            + $ledgerNet;

        return max(0, $quota - $used);
    }

    /**
     * @return list<string> Y-m-d weekday dates
     */
    public function expandWeekdayDates(?string $start, ?string $end): array
    {
        if (!$start || !$end) {
            return [];
        }

        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($to->lt($from)) {
            return [];
        }

        $out = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            if ($day->isWeekday()) {
                $out[] = $day->format('Y-m-d');
            }
        }

        return $out;
    }

    public function countWeekdaysBetween(?string $start, ?string $end): int
    {
        return $this->countWeekdays($start, $end);
    }

    public function countCalendarDaysBetween(?string $start, ?string $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($to->lt($from)) {
            return 0;
        }

        return $from->diffInDays($to) + 1;
    }

    /**
     * Hari kerja cuti PHL (approved + pending) untuk rolling window.
     *
     * @return list<string> Y-m-d
     */
    public function phlLeaveWeekdayDates(
        MasterKaryawan $employee,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null
    ): array {
        return $this->leaveWeekdayDatesByKind($employee, 'phl', $excludeHrRequestId, $excludeLegacyId);
    }

    /**
     * @return array{submission_count: int, used_days: int}
     */
    public function specialLeaveUsageStats(
        MasterKaryawan $employee,
        int $specialLeaveTypeId,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null
    ): array {
        $submissionCount = 0;
        $usedDays = 0;
        $typeMeta = HrSpecialLeaveType::query()->find($specialLeaveTypeId);
        $durationUnit = $this->normalizeSpecialDurationUnit($typeMeta ? ($typeMeta->duration_unit ?? 'weekday') : 'weekday');

        if (HrTableMode::usesLegacyHrTables()) {
            $query = LeaveRequest::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where('type', 'Special Leave')
                ->where('special_leave_id', $specialLeaveTypeId)
                ->whereIn('status', [
                    WorkflowStatus::PENDING,
                    WorkflowStatus::APPROVED_ATASAN,
                    WorkflowStatus::APPROVED_HRD,
                ]);

            if ($excludeLegacyId !== null) {
                $query->where('id', '!=', $excludeLegacyId);
            }

            foreach ($query->get() as $row) {
                $submissionCount++;
                $usedDays += $this->countDaysForSpecialType($row->start_date, $row->end_date, $durationUnit);
            }

            return ['submission_count' => $submissionCount, 'used_days' => $usedDays];
        }

        $query = HrRequest::with('leaveDetail')
            ->where('karyawan_id', $employee->id)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->whereIn('status', [
                WorkflowStatus::PENDING,
                WorkflowStatus::APPROVED_ATASAN,
                WorkflowStatus::APPROVED_HRD,
            ]);

        if ($excludeHrRequestId !== null) {
            $query->where('id', '!=', $excludeHrRequestId);
        }

        foreach ($query->get() as $row) {
            $detail = $row->leaveDetail;
            if (!$detail || ($detail->leave_kind ?? '') !== 'special') {
                continue;
            }
            if ((int) ($detail->special_leave_type_id ?? 0) !== $specialLeaveTypeId) {
                continue;
            }
            $submissionCount++;
            $usedDays += $this->countDaysForSpecialType(
                $this->formatDateYmd($detail->start_date),
                $this->formatDateYmd($detail->end_date),
                $durationUnit
            );
        }

        return ['submission_count' => $submissionCount, 'used_days' => $usedDays];
    }

    private function normalizeSpecialDurationUnit(?string $unit): string
    {
        $u = strtolower(trim((string) $unit));
        if ($u === 'calendar' || $u === 'open') {
            return $u;
        }

        return 'weekday';
    }

    public function countDaysForSpecialType(?string $start, ?string $end, string $durationUnit): int
    {
        if ($durationUnit === 'calendar' || $durationUnit === 'open') {
            return $this->countCalendarDaysBetween($start, $end);
        }

        return $this->countWeekdaysBetween($start, $end);
    }

    /**
     * @return list<string> Y-m-d weekday
     */
    public function leaveWeekdayDatesByKind(
        MasterKaryawan $employee,
        string $leaveKind,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null
    ): array {
        $dates = [];
        foreach ($this->leaveRowsByKind($employee, $leaveKind, $excludeHrRequestId, $excludeLegacyId) as $row) {
            $dates = array_merge($dates, $this->expandWeekdayDates($row['start_date'] ?? null, $row['end_date'] ?? null));
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * @return list<array{start_date: ?string, end_date: ?string}>
     */
    private function leaveRowsByKind(
        MasterKaryawan $employee,
        string $leaveKind,
        ?int $excludeHrRequestId,
        ?int $excludeLegacyId
    ): array {
        $legacyType = $this->legacyTypeForLeaveKind($leaveKind);

        if (HrTableMode::usesLegacyHrTables()) {
            $query = LeaveRequest::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where('type', $legacyType)
                ->whereIn('status', [
                    WorkflowStatus::PENDING,
                    WorkflowStatus::APPROVED_ATASAN,
                    WorkflowStatus::APPROVED_HRD,
                ]);

            if ($excludeLegacyId !== null) {
                $query->where('id', '!=', $excludeLegacyId);
            }

            return $query->get()->map(function ($row) {
                return [
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                ];
            })->all();
        }

        $query = HrRequest::with('leaveDetail')
            ->where('karyawan_id', $employee->id)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->whereIn('status', [
                WorkflowStatus::PENDING,
                WorkflowStatus::APPROVED_ATASAN,
                WorkflowStatus::APPROVED_HRD,
            ]);

        if ($excludeHrRequestId !== null) {
            $query->where('id', '!=', $excludeHrRequestId);
        }

        $out = [];
        foreach ($query->get() as $row) {
            $detail = $row->leaveDetail;
            if (!$detail || ($detail->leave_kind ?? 'annual') !== $leaveKind) {
                continue;
            }
            $out[] = [
                'start_date' => $this->formatDateYmd($detail->start_date),
                'end_date' => $this->formatDateYmd($detail->end_date),
            ];
        }

        return $out;
    }

    private function legacyTypeForLeaveKind(string $leaveKind): string
    {
        if ($leaveKind === 'special') {
            return 'Special Leave';
        }
        if ($leaveKind === 'unpaid') {
            return 'Unpaid Leave';
        }
        if ($leaveKind === 'phl') {
            return 'Holiday Replacement Leave';
        }

        return 'Annual Leave';
    }

    /**
     * Semua hari kerja cuti tahunan (pending + approved pasca cutover) pada periode aktif.
     *
     * @return list<string>
     */
    public function annualLeaveWeekdayDates(
        MasterKaryawan $employee,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null
    ): array {
        $period = $this->ensureActivePeriod($employee);
        $dates = [];

        foreach ($this->approvedAnnualLeaveRows($employee, $period) as $row) {
            if ($excludeHrRequestId !== null && (int) ($row['id'] ?? 0) === $excludeHrRequestId) {
                continue;
            }
            $dates = array_merge($dates, $this->expandWeekdayDates($row['start_date'] ?? null, $row['end_date'] ?? null));
        }

        foreach ($this->pendingAnnualLeaveRows($employee, $period, $excludeHrRequestId, $excludeLegacyId) as $row) {
            $dates = array_merge($dates, $this->expandWeekdayDates($row['start_date'] ?? null, $row['end_date'] ?? null));
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    public function systemUsedDays(MasterKaryawan $employee, HrLeaveBalancePeriod $period): int
    {
        $cutover = $this->cutoverAt();
        $periodStart = $this->periodStartAt($period);
        $periodEnd = $this->periodEndAt($period);

        if (HrTableMode::usesLegacyHrTables()) {
            return $this->systemUsedDaysLegacy($employee, $periodStart, $periodEnd, $cutover);
        }

        return $this->systemUsedDaysHr($employee, $periodStart, $periodEnd, $cutover);
    }

    /**
     * @return Collection<int, MasterKaryawan>
     */
    public function activeEmployeesForRekap(): Collection
    {
        return MasterKaryawan::query()
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get();
    }

    public function rekapRow(MasterKaryawan $employee): array
    {
        $summary = $this->summary($employee);
        $divisi = $employee->department->nama_divisi ?? $employee->divisi->nama_divisi ?? '';

        return [
            'karyawan_id' => $employee->id,
            'nik' => $employee->nik_karyawan,
            'nama_lengkap' => $employee->nama_lengkap,
            'organization_unit' => $divisi,
            'status_karyawan' => $employee->status_karyawan,
            'period_start' => $summary['period_start'],
            'period_end' => $summary['period_end'],
            'quota_days' => $summary['quota_days'],
            'opening_used_days' => $summary['opening_used_days'],
            'system_used_days' => $summary['system_used_days'],
            'pending_used_days' => $summary['pending_used_days'],
            'ledger_used_days' => $summary['ledger_used_days'],
            'alpa_days' => $summary['alpa_days'],
            'used_days' => $summary['used_days'],
            'remaining_days' => $summary['remaining_days'],
            'eligible' => $summary['eligible'],
        ];
    }

    private function systemUsedDaysHr(MasterKaryawan $employee, Carbon $periodStart, Carbon $periodEnd, Carbon $cutover): int
    {
        $rows = HrRequest::with('leaveDetail')
            ->where('karyawan_id', $employee->id)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->whereIn('status', [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD])
            ->where(function ($q) use ($periodStart, $periodEnd, $cutover) {
                $q->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->where('created_at', '>=', $cutover);
            })
            ->get();

        $total = 0;
        foreach ($rows as $row) {
            $detail = $row->leaveDetail;
            if (!$detail || ($detail->leave_kind ?? 'annual') !== 'annual') {
                continue;
            }
            $total += $this->countWeekdays(
                $this->formatDateYmd($detail->start_date),
                $this->formatDateYmd($detail->end_date)
            );
        }

        return $total;
    }

    private function systemUsedDaysLegacy(MasterKaryawan $employee, Carbon $periodStart, Carbon $periodEnd, Carbon $cutover): int
    {
        $rows = LeaveRequest::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('type', 'Annual Leave')
            ->whereIn('status', [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD])
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->where('created_at', '>=', $cutover)
            ->get();

        $total = 0;
        foreach ($rows as $row) {
            $total += $this->countWeekdays($row->start_date, $row->end_date);
        }

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingAnnualLeaveWeekdays(
        MasterKaryawan $employee,
        HrLeaveBalancePeriod $period,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null
    ): int {
        $total = 0;
        foreach ($this->pendingAnnualLeaveRows($employee, $period, $excludeHrRequestId, $excludeLegacyId) as $row) {
            $total += $this->countWeekdays($row['start_date'] ?? null, $row['end_date'] ?? null);
        }

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingAnnualLeaveRows(
        MasterKaryawan $employee,
        HrLeaveBalancePeriod $period,
        ?int $excludeHrRequestId = null,
        ?int $excludeLegacyId = null
    ): array {
        $periodStart = $this->periodStartAt($period);
        $periodEnd = $this->periodEndAt($period);

        if (HrTableMode::usesLegacyHrTables()) {
            $query = LeaveRequest::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where('type', 'Annual Leave')
                ->where('status', WorkflowStatus::PENDING)
                ->whereBetween('created_at', [$periodStart, $periodEnd]);

            if ($excludeLegacyId !== null) {
                $query->where('id', '!=', $excludeLegacyId);
            }

            return $query->orderByDesc('id')->get()->map(function ($row) {
                return [
                    'id' => $row->id,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                ];
            })->all();
        }

        $query = HrRequest::with('leaveDetail')
            ->where('karyawan_id', $employee->id)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->where('status', WorkflowStatus::PENDING)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        if ($excludeHrRequestId !== null) {
            $query->where('id', '!=', $excludeHrRequestId);
        }

        $rows = $query->orderByDesc('id')->get();
        $out = [];
        foreach ($rows as $row) {
            $detail = $row->leaveDetail;
            if (!$detail || ($detail->leave_kind ?? 'annual') !== 'annual') {
                continue;
            }
            $out[] = [
                'id' => $row->id,
                'start_date' => $this->formatDateYmd($detail->start_date),
                'end_date' => $this->formatDateYmd($detail->end_date),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function approvedAnnualLeaveRows(MasterKaryawan $employee, HrLeaveBalancePeriod $period): array
    {
        $cutover = $this->cutoverAt();
        $periodStart = $this->periodStartAt($period);
        $periodEnd = $this->periodEndAt($period);

        if (HrTableMode::usesLegacyHrTables()) {
            $models = LeaveRequest::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where('type', 'Annual Leave')
                ->whereIn('status', [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD])
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->where('created_at', '>=', $cutover)
                ->orderByDesc('id')
                ->get();

            return $models->map(function ($row) {
                return [
                    'id' => $row->id,
                    'no_document' => $row->no_document,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'status' => $row->status,
                    'title' => 'Cuti tahunan',
                ];
            })->all();
        }

        $rows = HrRequest::with('leaveDetail')
            ->where('karyawan_id', $employee->id)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->whereIn('status', [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD])
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->where('created_at', '>=', $cutover)
            ->orderByDesc('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $detail = $row->leaveDetail;
            if (!$detail || ($detail->leave_kind ?? 'annual') !== 'annual') {
                continue;
            }
            $out[] = [
                'id' => $row->id,
                'no_document' => $row->no_document,
                'start_date' => $this->formatDateYmd($detail->start_date),
                'end_date' => $this->formatDateYmd($detail->end_date),
                'status' => $row->status,
                'title' => 'Cuti tahunan',
            ];
        }

        return $out;
    }

    private function cutoverAt(): Carbon
    {
        $raw = config('greatday.leave_balance_cutover_at');
        if (!$raw) {
            return Carbon::create(2026, 4, 1, 0, 0, 0);
        }

        return Carbon::parse($raw)->startOfDay();
    }

    private function joinDate(MasterKaryawan $employee): ?Carbon
    {
        if (!$employee->tgl_mulai_kerja) {
            return null;
        }

        return Carbon::parse($employee->tgl_mulai_kerja)->startOfDay();
    }

    private function normalizeStatusKey(?string $status): string
    {
        $s = strtolower(trim((string) $status));
        if ($s === '') {
            return '';
        }
        if (str_contains($s, 'kontrak') || $s === 'contract') {
            return 'contract';
        }
        if (str_contains($s, 'permanen') || str_contains($s, 'permanent') || str_contains($s, 'tetap')) {
            return 'permanent';
        }
        if (str_contains($s, 'khusus')) {
            return 'khusus';
        }

        return $s;
    }

    private function completeMonthsBetween(Carbon $from, Carbon $to): int
    {
        if ($to->lt($from)) {
            return 0;
        }

        $months = $from->diffInMonths($to);
        if ($from->copy()->addMonths($months)->gt($to)) {
            $months--;
        }

        return max(0, $months);
    }

    private function countWeekdays(?string $start, ?string $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($to->lt($from)) {
            return 0;
        }

        $days = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            if ($day->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }

    private function formatDateRange(?string $start, ?string $end): ?string
    {
        if (!$start && !$end) {
            return null;
        }
        if ($start === $end || !$end) {
            return $start;
        }

        return $start . ' — ' . $end;
    }

    private function defaultResetLabel(HrLeaveBalancePeriod $period): string
    {
        $endCarbon = $this->asCarbon($period->period_end);
        $end = $endCarbon ? $endCarbon->format('d F Y') : null;

        return $end ? "Reset cuti per {$end}" : '';
    }

    private function asCarbon($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
    }

    private function formatDateYmd($value): ?string
    {
        $carbon = $this->asCarbon($value);

        return $carbon ? $carbon->format('Y-m-d') : null;
    }

    private function periodStartAt(HrLeaveBalancePeriod $period): Carbon
    {
        return $this->asCarbon($period->period_start)->startOfDay();
    }

    private function periodEndAt(HrLeaveBalancePeriod $period): Carbon
    {
        return $this->asCarbon($period->period_end)->endOfDay();
    }
}
