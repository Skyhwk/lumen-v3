<?php

namespace App\Services\Greatday;

use App\Models\Greatday\AttendanceCorrection;
use App\Models\Greatday\LeaveRequest;
use App\Models\Greatday\OvertimeRequest;
use App\Models\Greatday\OvertimeRequestMember;
use App\Models\Greatday\PermissionRequest;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\FormsPendingApprovalsService;
use App\Services\Greatday\GetBawahan;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\Presenters\AttendanceCorrectionPresenter;
use App\Services\Hr\Presenters\LeaveRequestPresenter;
use App\Services\Hr\Presenters\OvertimeRequestPresenter;
use App\Services\Hr\Presenters\PermissionRequestPresenter;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class FormsHubService
{
    private const ANNUAL_LEAVE_QUOTA = 12;

    /** @var array<string, array{title: string, controller: string}> */
    private const TYPE_META = [
        HrRequest::TYPE_LEAVE => ['title' => 'Leave Request', 'controller' => 'LeaveRequestsController'],
        HrRequest::TYPE_PERMISSION => ['title' => 'Permission Request', 'controller' => 'PermissionRequestsController'],
        HrRequest::TYPE_OVERTIME => ['title' => 'Overtime Request', 'controller' => 'OvertimeRequestsController'],
        HrRequest::TYPE_ATTENDANCE_CORRECTION => ['title' => 'Attendance Correction', 'controller' => 'AttendanceCorrectionsController'],
    ];

    /** @deprecated Gunakan stats() + listTab() */
    public function overview(MasterKaryawan $employee): array
    {
        return $this->stats($employee);
    }

    public function stats(MasterKaryawan $employee): array
    {
        if (!HrTableMode::usesLegacyHrTables()) {
            return $this->statsFromHr($employee);
        }

        return $this->statsFromLegacy($employee);
    }

    public function listTab(MasterKaryawan $employee, string $tab, int $page, int $perPage): array
    {
        if ($tab === 'approval') {
            return $this->listApprovalTab($employee, $page, $perPage);
        }

        if (!HrTableMode::usesLegacyHrTables()) {
            return $this->listTabFromHr($employee, $tab, $page, $perPage);
        }

        return $this->listTabFromLegacy($employee, $tab, $page, $perPage);
    }

    private function listApprovalTab(MasterKaryawan $employee, int $page, int $perPage): array
    {
        $all = app(FormsPendingApprovalsService::class)->pendingItems($employee);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($all, $offset, $perPage);
        $hasMore = count($all) > $offset + $perPage;

        return ['items' => $slice, 'has_more' => $hasMore];
    }

    private function statsFromHr(MasterKaryawan $employee): array
    {
        $ownBase = $this->ownHrRequestsQuery($employee, true);

        return [
            'sisa_cuti' => $this->remainingAnnualLeaveHr($employee),
            'jumlah_cuti' => $this->usedAnnualLeaveDaysHr($employee),
            'jumlah_lembur' => (clone $ownBase)
                ->where('request_type', HrRequest::TYPE_OVERTIME)
                ->count(),
            'jumlah_izin' => (clone $ownBase)
                ->where('request_type', HrRequest::TYPE_PERMISSION)
                ->count(),
            'counts' => [
                'submission' => (clone $ownBase)->where('status', WorkflowStatus::PENDING)->count(),
                'history' => (clone $this->formsHubHrQuery($employee, true, AtasanApprovalScope::isAtasanGrade($employee)))
                    ->where('status', '!=', WorkflowStatus::PENDING)
                    ->count(),
                'approval' => app(FormsPendingApprovalsService::class)->pendingCount($employee),
            ],
        ];
    }

    private function statsFromLegacy(MasterKaryawan $employee): array
    {
        $employeeId = (int) $employee->id;
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);

        $inWorkYear = fn ($query) => $query->whereBetween('created_at', [$periodFrom, $periodTo]);

        $submissionCount = $inWorkYear(LeaveRequest::where('is_active', true)->where('employee_id', $employeeId)->where('status', WorkflowStatus::PENDING))->count()
            + $inWorkYear(PermissionRequest::where('is_active', true)->where('employee_id', $employeeId)->where('status', WorkflowStatus::PENDING))->count()
            + $inWorkYear(AttendanceCorrection::where('is_active', true)->where('employee_id', $employeeId)->where('status', WorkflowStatus::PENDING))->count()
            + $this->legacyOwnOvertimeCount($employee, WorkflowStatus::PENDING, true);

        $historyCount = $this->legacyHistoryTabCount($employee);

        return [
            'sisa_cuti' => max(0, self::ANNUAL_LEAVE_QUOTA - $this->usedAnnualLeaveDaysLegacy($employee)),
            'jumlah_cuti' => $this->usedAnnualLeaveDaysLegacy($employee),
            'jumlah_lembur' => $this->countLegacyOvertimeTotal($employee, true),
            'jumlah_izin' => $inWorkYear(PermissionRequest::where('is_active', true)->where('employee_id', $employeeId))->count(),
            'counts' => [
                'submission' => $submissionCount,
                'history' => $historyCount,
                'approval' => app(FormsPendingApprovalsService::class)->pendingCount($employee),
            ],
        ];
    }

    private function listTabFromHr(MasterKaryawan $employee, string $tab, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $includeTeamHistory = $tab === 'history' && AtasanApprovalScope::isAtasanGrade($employee);

        $query = $this->formsHubHrQuery($employee, true, $includeTeamHistory)
            ->with($this->hrRequestRelationNames())
            ->orderByDesc('id');

        if ($tab === 'submission') {
            $query->where('status', WorkflowStatus::PENDING);
        } else {
            $query->where('status', '!=', WorkflowStatus::PENDING);
        }

        $rows = $query->skip($offset)->take($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->map(fn (HrRequest $row) => $this->mapHrRow($row, $employee))->values()->all();

        return ['items' => $items, 'has_more' => $hasMore];
    }

    private function listTabFromLegacy(MasterKaryawan $employee, string $tab, int $page, int $perPage): array
    {
        $all = $tab === 'history' && AtasanApprovalScope::isAtasanGrade($employee)
            ? $this->legacyTeamHistoryTabItems($employee)
            : $this->legacyOwnTabItems($employee, $tab);

        $offset = ($page - 1) * $perPage;
        $slice = array_slice($all, $offset, $perPage);
        $hasMore = count($all) > $offset + $perPage;

        return ['items' => $slice, 'has_more' => $hasMore];
    }

    /**
     * @param bool $currentWorkYearOnly Filter tampilan/count periode kerja (tgl_mulai_kerja), data DB tetap utuh
     * @param bool $includeTeamHistory Riwayat tim (GetBawahan) untuk atasan — selaras portal Request Lembur owner
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function formsHubHrQuery(MasterKaryawan $employee, bool $currentWorkYearOnly = false, bool $includeTeamHistory = false)
    {
        $query = HrRequest::query()
            ->where('is_active', true)
            ->where(function ($q) use ($employee, $includeTeamHistory) {
                $q->where(function ($own) use ($employee) {
                    $own->where('karyawan_id', $employee->id)
                        ->orWhere(function ($sub) use ($employee) {
                            $sub->where('request_type', HrRequest::TYPE_OVERTIME)
                                ->whereHas('overtimeParticipants', fn ($p) => $p->where('karyawan_id', $employee->id));
                        });
                });

                if ($includeTeamHistory) {
                    $this->applyTeamHistoryScope($q, $employee);
                }
            });

        if ($currentWorkYearOnly) {
            [$from, $to] = $this->leaveYearBounds($employee);
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query;
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function ownHrRequestsQuery(MasterKaryawan $employee, bool $currentWorkYearOnly = false)
    {
        return $this->formsHubHrQuery($employee, $currentWorkYearOnly, false);
    }

    /** @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query */
    private function applyTeamHistoryScope($query, MasterKaryawan $employee): void
    {
        if (!AtasanApprovalScope::isAtasanGrade($employee)) {
            return;
        }

        $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($employee);
        $subordinateNames = AtasanApprovalScope::subordinateKaryawanNames($employee);

        if (empty($subordinateIds) && empty($subordinateNames)) {
            return;
        }

        $query->orWhere(function ($team) use ($subordinateIds, $subordinateNames) {
            if (!empty($subordinateIds)) {
                $team->where(function ($inner) use ($subordinateIds) {
                    $inner->whereIn('karyawan_id', $subordinateIds)
                        ->whereIn('request_type', [
                            HrRequest::TYPE_LEAVE,
                            HrRequest::TYPE_PERMISSION,
                            HrRequest::TYPE_ATTENDANCE_CORRECTION,
                        ]);
                });
            }

            $team->orWhere(function ($inner) use ($subordinateIds, $subordinateNames) {
                $inner->where('request_type', HrRequest::TYPE_OVERTIME)
                    ->where(function ($ot) use ($subordinateIds, $subordinateNames) {
                        if (!empty($subordinateIds)) {
                            $ot->whereIn('karyawan_id', $subordinateIds)
                                ->orWhereHas('overtimeParticipants', fn ($p) => $p->whereIn('karyawan_id', $subordinateIds));
                        }
                        if (!empty($subordinateNames)) {
                            $ot->orWhereIn('created_by_name', $subordinateNames);
                        }
                    });
            });
        });
    }

    private function hrRequestRelationNames(): array
    {
        return [
            'leaveDetail',
            'permissionDetail',
            'overtimeDetail',
            'overtimeParticipants',
            'attendanceCorrectionDetail',
        ];
    }

    private function legacyOwnTabItems(MasterKaryawan $employee, string $tab): array
    {
        $employeeId = (int) $employee->id;
        $pendingOnly = $tab === 'submission';
        $items = [];
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);

        $applyStatus = function ($query) use ($pendingOnly) {
            if ($pendingOnly) {
                $query->where('status', WorkflowStatus::PENDING);
            } else {
                $query->where('status', '!=', WorkflowStatus::PENDING);
            }
        };

        $applyWorkYear = function ($query) use ($periodFrom, $periodTo) {
            $query->whereBetween('created_at', [$periodFrom, $periodTo]);
        };

        $leaves = LeaveRequest::with('specialLeaveType')
            ->where('is_active', true)
            ->where('employee_id', $employeeId)
            ->where($applyStatus)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyLeave($item));

        foreach ($leaves as $raw) {
            $items[] = $this->legacyItem('LeaveRequestsController', 'Leave Request', $raw);
        }

        $permissions = PermissionRequest::where('is_active', true)
            ->where('employee_id', $employeeId)
            ->where($applyStatus)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyEmployee($item));

        foreach ($permissions as $raw) {
            $items[] = $this->legacyItem('PermissionRequestsController', 'Permission Request', $raw);
        }

        $corrections = AttendanceCorrection::where('is_active', true)
            ->where('employee_id', $employeeId)
            ->where($applyStatus)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyEmployee($item));

        foreach ($corrections as $raw) {
            $items[] = $this->legacyItem('AttendanceCorrectionsController', 'Attendance Correction', $raw);
        }

        $overtimes = $this->legacyOwnOvertimeBaseQuery($employee, true)
            ->where($applyStatus)
            ->with('members')
            ->latest()
            ->get();

        foreach ($overtimes as $raw) {
            $items[] = $this->legacyItem('OvertimeRequestsController', 'Overtime Request', $raw);
        }

        usort($items, fn ($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

        return $items;
    }

    /** Riwayat non-pending: pengajuan sendiri + seluruh pohon GetBawahan (portal owner processed). */
    private function legacyTeamHistoryTabItems(MasterKaryawan $employee): array
    {
        $items = [];
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);
        $employeeIds = array_values(array_unique(array_merge(
            [(int) $employee->id],
            AtasanApprovalScope::subordinateKaryawanIds($employee)
        )));

        $applyHistory = fn ($query) => $query->where('status', '!=', WorkflowStatus::PENDING);
        $applyWorkYear = fn ($query) => $query->whereBetween('created_at', [$periodFrom, $periodTo]);

        $leaves = LeaveRequest::with('specialLeaveType')
            ->where('is_active', true)
            ->whereIn('employee_id', $employeeIds)
            ->where($applyHistory)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyLeave($item));

        foreach ($leaves as $raw) {
            $items[] = $this->legacyItem('LeaveRequestsController', 'Leave Request', $raw);
        }

        $permissions = PermissionRequest::where('is_active', true)
            ->whereIn('employee_id', $employeeIds)
            ->where($applyHistory)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyEmployee($item));

        foreach ($permissions as $raw) {
            $items[] = $this->legacyItem('PermissionRequestsController', 'Permission Request', $raw);
        }

        $corrections = AttendanceCorrection::where('is_active', true)
            ->whereIn('employee_id', $employeeIds)
            ->where($applyHistory)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyEmployee($item));

        foreach ($corrections as $raw) {
            $items[] = $this->legacyItem('AttendanceCorrectionsController', 'Attendance Correction', $raw);
        }

        $bawahanNames = GetBawahan::where('id', $employee->id)
            ->get()
            ->pluck('nama_lengkap')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();

        $overtimeQuery = OvertimeRequest::query()
            ->where('is_active', true)
            ->where($applyHistory)
            ->where($applyWorkYear);

        if (!empty($bawahanNames)) {
            $overtimeQuery->whereIn('created_by', $bawahanNames);
        } else {
            $overtimeQuery->whereRaw('0 = 1');
        }

        $overtimes = $overtimeQuery->with('members')->latest()->get();

        foreach ($overtimes as $raw) {
            $items[] = $this->legacyItem('OvertimeRequestsController', 'Overtime Request', $raw);
        }

        usort($items, fn ($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

        return $items;
    }

    private function legacyHistoryTabCount(MasterKaryawan $employee): int
    {
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);
        $inWorkYear = fn ($query) => $query->whereBetween('created_at', [$periodFrom, $periodTo]);
        $notPending = fn ($query) => $query->where('status', '!=', WorkflowStatus::PENDING);

        if (!AtasanApprovalScope::isAtasanGrade($employee)) {
            $employeeId = (int) $employee->id;

            return $inWorkYear(LeaveRequest::where('is_active', true)->where('employee_id', $employeeId)->where($notPending))->count()
                + $inWorkYear(PermissionRequest::where('is_active', true)->where('employee_id', $employeeId)->where($notPending))->count()
                + $inWorkYear(AttendanceCorrection::where('is_active', true)->where('employee_id', $employeeId)->where($notPending))->count()
                + $this->legacyOwnOvertimeCount($employee, 'history', true);
        }

        $employeeIds = array_values(array_unique(array_merge(
            [(int) $employee->id],
            AtasanApprovalScope::subordinateKaryawanIds($employee)
        )));

        $count = $inWorkYear(LeaveRequest::where('is_active', true)->whereIn('employee_id', $employeeIds)->where($notPending))->count()
            + $inWorkYear(PermissionRequest::where('is_active', true)->whereIn('employee_id', $employeeIds)->where($notPending))->count()
            + $inWorkYear(AttendanceCorrection::where('is_active', true)->whereIn('employee_id', $employeeIds)->where($notPending))->count();

        $bawahanNames = GetBawahan::where('id', $employee->id)
            ->get()
            ->pluck('nama_lengkap')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();

        if (!empty($bawahanNames)) {
            $count += $inWorkYear(OvertimeRequest::where('is_active', true)->where($notPending)->whereIn('created_by', $bawahanNames))->count();
        }

        return $count;
    }

    private function legacyOwnOvertimeCount(MasterKaryawan $employee, $statusMode, bool $currentWorkYearOnly = false): int
    {
        $query = $this->legacyOwnOvertimeBaseQuery($employee, $currentWorkYearOnly);

        if ($statusMode === WorkflowStatus::PENDING) {
            $query->where('status', WorkflowStatus::PENDING);
        } else {
            $query->where('status', '!=', WorkflowStatus::PENDING);
        }

        return $query->count();
    }

    private function bucketOwnRequest(array $item, array &$submissions, array &$history): void
    {
        if (($item['status'] ?? '') === WorkflowStatus::PENDING) {
            $submissions[] = $item;
        } else {
            $history[] = $item;
        }
    }

    private function mapHrRow(HrRequest $row, MasterKaryawan $viewer): array
    {
        $meta = self::TYPE_META[$row->request_type] ?? ['title' => 'HR Request', 'controller' => 'LeaveRequestsController'];
        switch ($row->request_type) {
            case HrRequest::TYPE_LEAVE:
                $raw = LeaveRequestPresenter::toGreatdayJson($row);
                break;
            case HrRequest::TYPE_PERMISSION:
                $raw = PermissionRequestPresenter::toGreatdayJson($row);
                break;
            case HrRequest::TYPE_OVERTIME:
                $raw = OvertimeRequestPresenter::toGreatdayJson($row);
                break;
            case HrRequest::TYPE_ATTENDANCE_CORRECTION:
                $raw = AttendanceCorrectionPresenter::toGreatdayJson($row);
                break;
            default:
                $raw = (object) ['id' => $row->id, 'status' => $row->status];
                break;
        }

        $karyawan = MasterKaryawan::find($row->karyawan_id);

        return [
            'id' => $row->id,
            'controller' => $meta['controller'],
            'title' => $meta['title'],
            'name' => $karyawan->nama_lengkap ?? 'Unknown',
            'position' => $karyawan->jabatan ?? $row->no_document,
            'description' => $row->description ?? $row->no_document,
            'status' => $row->status,
            'raw' => $raw,
            'can_approve' => $viewer->grade && in_array($viewer->grade, ['MANAGER', 'SUPERVISOR'], true)
                && $row->status === WorkflowStatus::PENDING
                && (int) $row->karyawan_id !== (int) $viewer->id,
        ];
    }

    private function legacyItem(string $controller, string $title, $model): array
    {
        return [
            'id' => $model->id,
            'controller' => $controller,
            'title' => $title,
            'name' => $model->employee_name ?? '',
            'position' => $model->employee_position ?? ($model->no_document ?? ''),
            'description' => $model->description ?? ($model->type ?? ''),
            'status' => $model->status,
            'raw' => $model,
            'can_approve' => false,
        ];
    }

    private function enrichLegacyLeave(LeaveRequest $item): LeaveRequest
    {
        $employee = MasterKaryawan::find($item->employee_id);
        $item->employee_name = $employee->nama_lengkap ?? '';
        $item->employee_position = $employee->jabatan ?? '';

        return $item;
    }

    private function enrichLegacyEmployee($item)
    {
        $employee = MasterKaryawan::find($item->employee_id);
        $item->employee_name = $employee->nama_lengkap ?? '';
        $item->employee_position = $employee->jabatan ?? '';

        return $item;
    }

    /**
     * Periode tahun kerja berjalan: ulang tahun kerja (tgl_mulai_kerja) s/d sebelum ulang tahun berikutnya.
     * Dipakai cuti, stat formulir, pengajuan/riwayat — hanya filter tampilan, tidak hapus DB.
     */
    private function leaveYearBounds(MasterKaryawan $employee): array
    {
        $join = $employee->tgl_mulai_kerja ? Carbon::parse($employee->tgl_mulai_kerja) : Carbon::now()->startOfYear();
        $today = Carbon::now()->startOfDay();
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

    private function usedAnnualLeaveDaysHr(MasterKaryawan $employee): int
    {
        [$from, $to] = $this->leaveYearBounds($employee);

        $rows = HrRequest::with('leaveDetail')
            ->where('karyawan_id', $employee->id)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->whereIn('status', [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD])
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $total = 0;
        foreach ($rows as $row) {
            $detail = $row->leaveDetail;
            if (!$detail || ($detail->leave_kind ?? 'annual') !== 'annual') {
                continue;
            }
            $total += $this->countWeekdays($detail->start_date, $detail->end_date);
        }

        return $total;
    }

    private function remainingAnnualLeaveHr(MasterKaryawan $employee): int
    {
        if (in_array($employee->status_karyawan ?? '', ['Training', 'Probation'], true)) {
            return 0;
        }

        return max(0, self::ANNUAL_LEAVE_QUOTA - $this->usedAnnualLeaveDaysHr($employee));
    }

    private function usedAnnualLeaveDaysLegacy(MasterKaryawan $employee): int
    {
        [$from, $to] = $this->leaveYearBounds($employee);

        $rows = LeaveRequest::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('type', 'Annual Leave')
            ->whereIn('status', [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD])
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $total = 0;
        foreach ($rows as $row) {
            $total += $this->countWeekdays($row->start_date, $row->end_date);
        }

        return $total;
    }

    private function countLegacyOvertimeTotal(MasterKaryawan $employee, bool $currentWorkYearOnly = false): int
    {
        return $this->legacyOwnOvertimeBaseQuery($employee, $currentWorkYearOnly)->count();
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function legacyOwnOvertimeBaseQuery(MasterKaryawan $employee, bool $currentWorkYearOnly = false)
    {
        $employeeId = (int) $employee->id;
        $memberOvertimeIds = OvertimeRequestMember::query()
            ->where('employee_id', $employeeId)
            ->pluck('overtime_request_id');

        $query = OvertimeRequest::query()
            ->where('is_active', true)
            ->where(function ($q) use ($employee, $memberOvertimeIds) {
                $q->where('created_by', $employee->nama_lengkap)
                    ->orWhereIn('id', $memberOvertimeIds);
            });

        if ($currentWorkYearOnly) {
            [$from, $to] = $this->leaveYearBounds($employee);
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query;
    }
}
