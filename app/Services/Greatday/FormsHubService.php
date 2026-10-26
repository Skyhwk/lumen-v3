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
use App\Services\Hr\HrFormAttachmentStorage;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrTableMode;
use App\Support\Greatday\GreatdayAssetPaths;
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

        if ($tab === 'approval_history') {
            return $this->listApprovalHistoryTab($employee, $page, $perPage);
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
        $leaveBalance = app(LeaveBalanceService::class)->summary($employee);

        return [
            'sisa_cuti' => $leaveBalance['sisa_cuti'],
            'jumlah_cuti' => $leaveBalance['jumlah_cuti'],
            'jumlah_lembur' => $this->countPersonalOvertimeHr($employee, true),
            'jumlah_izin' => $this->countPersonalPermissionHr($employee, true),
            'counts' => [
                'submission' => (clone $ownBase)->whereIn('status', WorkflowStatus::submitterInProgressStatuses())->count(),
                'history' => (clone $this->formsHubHrQuery($employee, true))
                    ->whereNotIn('status', WorkflowStatus::submitterInProgressStatuses())
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

        $submissionCount = $this->countLegacyOwnSubmissionsInProgress($employee);

        $historyCount = $this->legacyHistoryTabCount($employee);

        $leaveBalance = app(LeaveBalanceService::class)->summary($employee);

        return [
            'sisa_cuti' => $leaveBalance['sisa_cuti'],
            'jumlah_cuti' => $leaveBalance['jumlah_cuti'],
            'jumlah_lembur' => $this->countLegacyPersonalOvertime($employee, true),
            'jumlah_izin' => $this->countLegacyPersonalPermissionsApprovedHrd($employee),
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

        $query = $this->formsHubHrQuery($employee, true)
            ->with($this->hrRequestRelationNames())
            ->orderByDesc('id');

        if ($tab === 'submission') {
            $query->whereIn('status', WorkflowStatus::submitterInProgressStatuses());
        } else {
            $query->whereNotIn('status', WorkflowStatus::submitterInProgressStatuses());
        }

        $rows = $query->skip($offset)->take($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->map(fn (HrRequest $row) => $this->mapHrRow($row, $employee))->values()->all();

        return ['items' => $items, 'has_more' => $hasMore];
    }

    private function listTabFromLegacy(MasterKaryawan $employee, string $tab, int $page, int $perPage): array
    {
        $all = $this->legacyOwnTabItems($employee, $tab);

        $offset = ($page - 1) * $perPage;
        $slice = array_slice($all, $offset, $perPage);
        $hasMore = count($all) > $offset + $perPage;

        return ['items' => $slice, 'has_more' => $hasMore];
    }

    /**
     * Pengajuan/riwayat milik user: baris dengan karyawan_id = pengaju (termasuk lembur yang dia buat).
     * Anggota lembur saja tidak masuk — sudah diwakili kartu jumlah lembur.
     *
     * @param bool $currentWorkYearOnly Filter tampilan/count periode kerja (tgl_mulai_kerja), data DB tetap utuh
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function formsHubHrQuery(MasterKaryawan $employee, bool $currentWorkYearOnly = false)
    {
        $query = HrRequest::query()
            ->where('is_active', true)
            ->where(function ($q) use ($employee) {
                $this->applyOwnFormsHubSubmissionScope($q, $employee);
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
        return $this->formsHubHrQuery($employee, $currentWorkYearOnly);
    }

    private function hrRequestRelationNames(): array
    {
        return [
            'leaveDetail',
            'permissionDetail',
            'overtimeDetail',
            'overtimeParticipants',
            'attendanceCorrectionDetail',
            'approvalSteps',
        ];
    }

    private function legacyOwnTabItems(MasterKaryawan $employee, string $tab): array
    {
        $employeeId = (int) $employee->id;
        $submissionTab = $tab === 'submission';
        $items = [];
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);

        $applyWorkYear = function ($query) use ($periodFrom, $periodTo) {
            $query->whereBetween('created_at', [$periodFrom, $periodTo]);
        };

        $leaves = LeaveRequest::with('specialLeaveType')
            ->where('is_active', true)
            ->where('employee_id', $employeeId)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->filter(fn ($item) => $this->legacyRowMatchesTab('leave_requests', $item, $submissionTab))
            ->map(fn ($item) => $this->enrichLegacyLeave($this->applyEffectiveLegacyStatus('leave_requests', $item)));

        foreach ($leaves as $raw) {
            $items[] = $this->legacyItem('LeaveRequestsController', 'Leave Request', $raw);
        }

        $permissions = PermissionRequest::where('is_active', true)
            ->where('employee_id', $employeeId)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->filter(fn ($item) => $this->legacyRowMatchesTab('permission_requests', $item, $submissionTab))
            ->map(fn ($item) => $this->enrichLegacyEmployee($this->applyEffectiveLegacyStatus('permission_requests', $item)));

        foreach ($permissions as $raw) {
            $items[] = $this->legacyItem('PermissionRequestsController', 'Permission Request', $raw);
        }

        $corrections = AttendanceCorrection::where('is_active', true)
            ->where('employee_id', $employeeId)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->filter(fn ($item) => $this->legacyRowMatchesTab('attendance_corrections', $item, $submissionTab))
            ->map(fn ($item) => $this->enrichLegacyEmployee(
                $this->applyEffectiveLegacyStatus('attendance_corrections', $item),
                GreatdayAssetPaths::KEY_KOREKSI_ABSEN
            ));

        foreach ($corrections as $raw) {
            $items[] = $this->legacyItem('AttendanceCorrectionsController', 'Attendance Correction', $raw);
        }

        $overtimes = $this->legacySubmittedOvertimeBaseQuery($employee, true)
            ->with('members')
            ->latest()
            ->get()
            ->filter(fn ($item) => $this->legacyRowMatchesTab('overtime_requests', $item, $submissionTab))
            ->map(fn ($item) => $this->applyEffectiveLegacyStatus('overtime_requests', $item));

        foreach ($overtimes as $raw) {
            if (!$this->legacyOvertimeIsSubmittedBy($raw, $employee)) {
                continue;
            }
            $items[] = $this->legacyItem('OvertimeRequestsController', 'Overtime Request', $this->enrichLegacyOvertime($raw));
        }

        usort($items, fn ($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

        return $items;
    }

    private function listApprovalHistoryTab(MasterKaryawan $employee, int $page, int $perPage): array
    {
        if (!HrTableMode::usesLegacyHrTables()) {
            return $this->listApprovalHistoryFromHr($employee, $page, $perPage);
        }

        $all = $this->legacyTeamApprovalHistoryItems($employee);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($all, $offset, $perPage);
        $hasMore = count($all) > $offset + $perPage;

        return ['items' => $slice, 'has_more' => $hasMore];
    }

    private function listApprovalHistoryFromHr(MasterKaryawan $employee, int $page, int $perPage): array
    {
        $subordinateIds = AtasanApprovalScope::subordinateKaryawanIdsForTeamHistory($employee);
        if ($subordinateIds === []) {
            return ['items' => [], 'has_more' => false];
        }

        $offset = ($page - 1) * $perPage;
        [$from, $to] = $this->leaveYearBounds($employee);

        $query = HrRequest::query()
            ->where('is_active', true)
            ->whereIn('karyawan_id', $subordinateIds)
            ->whereIn('request_type', [
                HrRequest::TYPE_LEAVE,
                HrRequest::TYPE_PERMISSION,
                HrRequest::TYPE_ATTENDANCE_CORRECTION,
            ])
            ->whereNotIn('status', WorkflowStatus::submitterInProgressStatuses())
            ->whereBetween('created_at', [$from, $to])
            ->with($this->hrRequestRelationNames())
            ->orderByDesc('id');

        $rows = $query->skip($offset)->take($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->map(fn (HrRequest $row) => $this->mapHrRow($row, $employee))->values()->all();

        return ['items' => $items, 'has_more' => $hasMore];
    }

    /** Riwayat persetujuan tim: cuti/izin/koreksi bawahan (selesai), tanpa pengajuan sendiri. */
    private function legacyTeamApprovalHistoryItems(MasterKaryawan $employee): array
    {
        $subordinateIds = AtasanApprovalScope::subordinateKaryawanIdsForTeamHistory($employee);
        if ($subordinateIds === []) {
            return [];
        }

        $items = [];
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);

        $applyHistory = fn ($query) => $query->whereNotIn('status', WorkflowStatus::submitterInProgressStatuses());
        $applyWorkYear = fn ($query) => $query->whereBetween('created_at', [$periodFrom, $periodTo]);

        $leaves = LeaveRequest::with('specialLeaveType')
            ->where('is_active', true)
            ->whereIn('employee_id', $subordinateIds)
            ->where($applyHistory)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyLeave($item));

        foreach ($leaves as $raw) {
            $items[] = $this->legacyItem('LeaveRequestsController', 'Leave Request', $raw);
        }

        $permissions = PermissionRequest::where('is_active', true)
            ->whereIn('employee_id', $subordinateIds)
            ->where($applyHistory)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyEmployee($item));

        foreach ($permissions as $raw) {
            $items[] = $this->legacyItem('PermissionRequestsController', 'Permission Request', $raw);
        }

        $corrections = AttendanceCorrection::where('is_active', true)
            ->whereIn('employee_id', $subordinateIds)
            ->where($applyHistory)
            ->where($applyWorkYear)
            ->latest()
            ->get()
            ->map(fn ($item) => $this->enrichLegacyEmployee($item));

        foreach ($corrections as $raw) {
            $items[] = $this->legacyItem('AttendanceCorrectionsController', 'Attendance Correction', $raw);
        }

        usort($items, fn ($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

        return $items;
    }

    private function legacyHistoryTabCount(MasterKaryawan $employee): int
    {
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);
        $inWorkYear = fn ($query) => $query->whereBetween('created_at', [$periodFrom, $periodTo]);
        $finished = fn ($query) => $query->whereNotIn('status', WorkflowStatus::submitterInProgressStatuses());
        $employeeId = (int) $employee->id;

        return $inWorkYear(LeaveRequest::where('is_active', true)->where('employee_id', $employeeId)->where($finished))->count()
            + $inWorkYear(PermissionRequest::where('is_active', true)->where('employee_id', $employeeId)->where($finished))->count()
            + $inWorkYear(AttendanceCorrection::where('is_active', true)->where('employee_id', $employeeId)->where($finished))->count()
            + $this->legacyOwnOvertimeCount($employee, 'history', true);
    }

    private function legacyOwnOvertimeCount(MasterKaryawan $employee, $statusMode, bool $currentWorkYearOnly = false): int
    {
        $query = $this->legacySubmittedOvertimeBaseQuery($employee, $currentWorkYearOnly);

        if ($statusMode === 'submission') {
            $query->whereIn('status', WorkflowStatus::submitterInProgressStatuses());
        } else {
            $query->whereNotIn('status', WorkflowStatus::submitterInProgressStatuses());
        }

        return $query->count();
    }

    private function effectiveLegacyStatus(string $legacyTable, $row): string
    {
        return HrRequestResolver::hrStatusForLegacyRow($legacyTable, (int) $row->id) ?? $row->status;
    }

    private function applyEffectiveLegacyStatus(string $legacyTable, $row)
    {
        $row->status = $this->effectiveLegacyStatus($legacyTable, $row);

        return $row;
    }

    private function legacyRowMatchesTab(string $legacyTable, $row, bool $submissionTab): bool
    {
        $inProgress = WorkflowStatus::isSubmitterInProgress($this->effectiveLegacyStatus($legacyTable, $row));

        return $submissionTab ? $inProgress : !$inProgress;
    }

    private function countLegacyOwnSubmissionsInProgress(MasterKaryawan $employee): int
    {
        $employeeId = (int) $employee->id;
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);
        $inWorkYear = fn ($query) => $query->whereBetween('created_at', [$periodFrom, $periodTo]);

        $count = 0;

        foreach ($inWorkYear(LeaveRequest::where('is_active', true)->where('employee_id', $employeeId))->get() as $row) {
            if ($this->legacyRowMatchesTab('leave_requests', $row, true)) {
                $count++;
            }
        }
        foreach ($inWorkYear(PermissionRequest::where('is_active', true)->where('employee_id', $employeeId))->get() as $row) {
            if ($this->legacyRowMatchesTab('permission_requests', $row, true)) {
                $count++;
            }
        }
        foreach ($inWorkYear(AttendanceCorrection::where('is_active', true)->where('employee_id', $employeeId))->get() as $row) {
            if ($this->legacyRowMatchesTab('attendance_corrections', $row, true)) {
                $count++;
            }
        }
        foreach ($this->legacySubmittedOvertimeBaseQuery($employee, true)->get() as $row) {
            if ($this->legacyRowMatchesTab('overtime_requests', $row, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function bucketOwnRequest(array $item, array &$submissions, array &$history): void
    {
        if (WorkflowStatus::isSubmitterInProgress($item['status'] ?? null)) {
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
        $displayName = $karyawan->nama_lengkap ?? null;
        if ($displayName === null || $displayName === '') {
            $displayName = $this->resolveHrHubDisplayName($row);
        }

        return [
            'id' => $row->id,
            'controller' => $meta['controller'],
            'title' => $meta['title'],
            'name' => $displayName ?? 'Unknown',
            'position' => $karyawan->jabatan ?? $row->no_document,
            'description' => $row->description ?? $row->no_document,
            'status' => $row->status,
            'raw' => $raw,
            'can_approve' => app(\App\Services\Hr\HrApprovalChainService::class)->viewerCanApprove($row, $viewer),
        ];
    }

    private function resolveHrHubDisplayName(HrRequest $row): ?string
    {
        if (is_string($row->created_by_name) && $row->created_by_name !== '') {
            return $row->created_by_name;
        }

        if ($row->request_type === HrRequest::TYPE_OVERTIME) {
            $row->loadMissing('overtimeParticipants');
            foreach ($row->overtimeParticipants as $participant) {
                $member = MasterKaryawan::find($participant->karyawan_id);
                if ($member && ($member->nama_lengkap ?? '') !== '') {
                    return $member->nama_lengkap;
                }
            }

            if ($row->id_department) {
                $divisi = \App\Models\MasterDivisi::find($row->id_department);

                return $divisi->nama_divisi ?? null;
            }
        }

        return null;
    }

    private function enrichLegacyOvertime(OvertimeRequest $item): OvertimeRequest
    {
        if (!empty($item->department_name)) {
            $item->employee_name = $item->department_name;

            return $item;
        }

        $firstMember = $item->members->first();
        if ($firstMember && ($firstMember->employee_name ?? '') !== '') {
            $item->employee_name = $firstMember->employee_name;

            return $item;
        }

        if (is_string($item->created_by) && $item->created_by !== '') {
            $item->employee_name = $item->created_by;
        }

        return $item;
    }

    private function legacyItem(string $controller, string $title, $model): array
    {
        $name = $model->employee_name ?? '';
        if ($name === '' && $controller === 'OvertimeRequestsController') {
            $name = $model->department_name ?? $model->created_by ?? '';
        }

        return [
            'id' => $model->id,
            'controller' => $controller,
            'title' => $title,
            'name' => $name,
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
        $this->applyLegacyAttachmentUrls($item, GreatdayAssetPaths::KEY_CUTI);

        return $item;
    }

    private function enrichLegacyEmployee($item, string $assetKey = GreatdayAssetPaths::KEY_IZIN)
    {
        $employee = MasterKaryawan::find($item->employee_id);
        $item->employee_name = $employee->nama_lengkap ?? '';
        $item->employee_position = $employee->jabatan ?? '';
        $this->applyLegacyAttachmentUrls($item, $assetKey);

        return $item;
    }

    private function applyLegacyAttachmentUrls($item, string $assetKey): void
    {
        $stored = $item->attachment ?? null;
        $urls = HrFormAttachmentStorage::resolvePublicUrls($stored, $assetKey);
        $item->attachments = $urls;
        $item->attachment = $urls[0] ?? null;
    }

    /**
     * Periode tahun kerja berjalan: ulang tahun kerja (tgl_mulai_kerja) s/d sebelum ulang tahun berikutnya.
     * Dipakai cuti, stat formulir, pengajuan/riwayat — hanya filter tampilan, tidak hapus DB.
     */
    private function leaveYearBounds(MasterKaryawan $employee): array
    {
        return app(LeaveBalanceService::class)->leaveYearBounds($employee);
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
            if (!$detail || !LeaveBalanceService::countsTowardAnnualBalance($detail->leave_kind ?? null)) {
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
            ->whereIn('type', ['Annual Leave', 'Urgent Leave'])
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

    /** Kartu stat formulir — lembur saya (peserta aktif), bukan seluruh pengajuan tim sebagai pembuat. */
    private function countPersonalOvertimeHr(MasterKaryawan $employee, bool $currentWorkYearOnly = false): int
    {
        $query = HrRequest::query()
            ->where('is_active', true)
            ->where('request_type', HrRequest::TYPE_OVERTIME)
            ->whereHas('overtimeParticipants', function ($p) use ($employee) {
                $p->where('karyawan_id', $employee->id)->where('is_active', true);
            });

        if ($currentWorkYearOnly) {
            [$from, $to] = $this->leaveYearBounds($employee);
            $query->whereBetween('created_at', [$from, $to]);
        }

        $query->whereIn('status', WorkflowStatus::formsStatApprovedOvertimeStatuses());

        return $query->count();
    }

    /** Kartu stat formulir — izin saya (pengaju), bukan bawahan. */
    private function countPersonalPermissionHr(MasterKaryawan $employee, bool $currentWorkYearOnly = false): int
    {
        $query = HrRequest::query()
            ->where('is_active', true)
            ->where('request_type', HrRequest::TYPE_PERMISSION)
            ->where('karyawan_id', $employee->id)
            ->whereIn('status', WorkflowStatus::formsStatApprovedStatuses());

        if ($currentWorkYearOnly) {
            [$from, $to] = $this->leaveYearBounds($employee);
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query->count();
    }

    /** Kartu stat formulir — lembur saya via membership, bukan semua yang pernah dibuat atasan untuk tim. */
    private function countLegacyPersonalOvertime(MasterKaryawan $employee, bool $currentWorkYearOnly = false): int
    {
        $employeeId = (int) $employee->id;
        $memberOvertimeIds = OvertimeRequestMember::query()
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->pluck('overtime_request_id');

        if ($memberOvertimeIds->isEmpty()) {
            return 0;
        }

        $query = OvertimeRequest::query()
            ->where('is_active', true)
            ->whereIn('id', $memberOvertimeIds);

        if ($currentWorkYearOnly) {
            [$from, $to] = $this->leaveYearBounds($employee);
            $query->whereBetween('created_at', [$from, $to]);
        }

        $count = 0;
        foreach ($query->get() as $row) {
            if (WorkflowStatus::countsTowardFormsUserStat(
                $this->effectiveLegacyStatus('overtime_requests', $row),
                'overtime'
            )) {
                $count++;
            }
        }

        return $count;
    }

    private function countLegacyPersonalPermissionsApprovedHrd(MasterKaryawan $employee): int
    {
        $employeeId = (int) $employee->id;
        [$periodFrom, $periodTo] = $this->leaveYearBounds($employee);
        $count = 0;

        foreach (
            PermissionRequest::where('is_active', true)
                ->where('employee_id', $employeeId)
                ->whereBetween('created_at', [$periodFrom, $periodTo])
                ->get() as $row
        ) {
            if (WorkflowStatus::countsTowardFormsUserStat(
                $this->effectiveLegacyStatus('permission_requests', $row),
                'standard'
            )) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Tab pengajuan/riwayat: cuti/izin/koreksi = karyawan_id pengaju.
     * Lembur = pembuat (created_by_*), bukan anggota saja — termasuk data migrasi yang karyawan_id-nya salah.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    private function applyOwnFormsHubSubmissionScope($query, MasterKaryawan $employee): void
    {
        $employeeId = (int) $employee->id;
        $employeeName = trim((string) ($employee->nama_lengkap ?? ''));

        $query->where(function ($q) use ($employeeId, $employeeName) {
            $q->where(function ($standard) use ($employeeId) {
                $standard->where('request_type', '!=', HrRequest::TYPE_OVERTIME)
                    ->where('karyawan_id', $employeeId);
            })->orWhere(function ($overtime) use ($employeeId, $employeeName) {
                $overtime->where('request_type', HrRequest::TYPE_OVERTIME)
                    ->where(function ($creator) use ($employeeId, $employeeName) {
                        $creator->where('created_by_karyawan_id', $employeeId);

                        if ($employeeName !== '') {
                            $creator->orWhere('created_by_name', $employeeName);
                        }

                        $creator->orWhere(function ($trustedHeader) use ($employeeId, $employeeName) {
                            $trustedHeader->where('karyawan_id', $employeeId)
                                ->whereNull('created_by_karyawan_id')
                                ->where(function ($name) use ($employeeName) {
                                    $name->whereNull('created_by_name');
                                    if ($employeeName !== '') {
                                        $name->orWhere('created_by_name', $employeeName);
                                    }
                                });
                        });
                    });
            });
        });
    }

    private function legacyOvertimeIsSubmittedBy(OvertimeRequest $row, MasterKaryawan $employee): bool
    {
        $creatorName = trim((string) ($row->created_by ?? ''));
        $employeeName = trim((string) ($employee->nama_lengkap ?? ''));

        if ($employeeName === '' || $creatorName === '') {
            return false;
        }

        return strcasecmp($creatorName, $employeeName) === 0;
    }

    /** Lembur yang user ajukan (pembuat), bukan hanya tercatat sebagai anggota. */
    private function legacySubmittedOvertimeBaseQuery(MasterKaryawan $employee, bool $currentWorkYearOnly = false)
    {
        $employeeName = trim((string) ($employee->nama_lengkap ?? ''));

        $query = OvertimeRequest::query()->where('is_active', true);

        if ($employeeName !== '') {
            $query->whereRaw('LOWER(TRIM(created_by)) = ?', [mb_strtolower($employeeName)]);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($currentWorkYearOnly) {
            [$from, $to] = $this->leaveYearBounds($employee);
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query;
    }

    /** @deprecated Hanya dipakai stat total internal; tab pakai legacySubmittedOvertimeBaseQuery */
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
