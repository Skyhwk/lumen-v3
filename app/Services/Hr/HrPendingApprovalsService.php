<?php

namespace App\Services\Hr;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\PendingApprovalSummary;
use App\Services\Hr\Presenters\AttendanceCorrectionPresenter;
use App\Services\Hr\Presenters\LeaveRequestPresenter;
use App\Services\Hr\Presenters\OvertimeRequestPresenter;
use App\Services\Hr\Presenters\PermissionRequestPresenter;

class HrPendingApprovalsService
{
    /** @var array<string, array{title: string, controller: string}> */
    private const TYPE_META = [
        HrRequest::TYPE_LEAVE => ['title' => 'Leave Request', 'controller' => 'LeaveRequestsController'],
        HrRequest::TYPE_PERMISSION => ['title' => 'Permission Request', 'controller' => 'PermissionRequestsController'],
        HrRequest::TYPE_OVERTIME => ['title' => 'Overtime Request', 'controller' => 'OvertimeRequestsController'],
        HrRequest::TYPE_ATTENDANCE_CORRECTION => ['title' => 'Attendance Correction', 'controller' => 'AttendanceCorrectionsController'],
    ];

    public function listForAtasan(MasterKaryawan $approver): array
    {
        if (!AtasanApprovalScope::isAtasanGrade($approver)) {
            return [];
        }

        $requestIds = \App\Models\Hr\HrApprovalStep::query()
            ->where('step', \App\Models\Hr\HrApprovalStep::STEP_ATASAN)
            ->where('state', \App\Models\Hr\HrApprovalStep::STATE_PENDING)
            ->where('expected_karyawan_id', (int) $approver->id)
            ->pluck('request_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($requestIds === []) {
            return [];
        }

        $rows = HrRequest::query()
            ->with(['leaveDetail', 'permissionDetail', 'attendanceCorrectionDetail', 'overtimeDetail', 'overtimeParticipants'])
            ->whereIn('id', $requestIds)
            ->where('status', WorkflowStatus::PENDING)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        $chain = app(HrApprovalChainService::class);
        $pending = [];
        foreach ($rows as $row) {
            if (!$chain->viewerCanApprove($row, $approver)) {
                continue;
            }
            $pending[] = $this->mapRow($row, $approver);
        }

        return $pending;
    }

    private function mapRow(HrRequest $row, MasterKaryawan $viewer): array
    {
        $meta = self::TYPE_META[$row->request_type] ?? ['title' => 'HR Request', 'controller' => 'HrRequestController'];
        $karyawan = MasterKaryawan::find($row->karyawan_id);
        $raw = $this->presentRaw($row);
        $controller = $meta['controller'];
        $canApprove = app(HrApprovalChainService::class)->viewerCanApprove($row, $viewer);

        return [
            'id' => $row->id,
            'controller' => $controller,
            'title' => $meta['title'],
            'name' => $karyawan->nama_lengkap ?? 'Unknown',
            'position' => $karyawan->jabatan ?? $row->no_document,
            'description' => $row->description ?? $row->request_type,
            'status' => $row->status,
            'raw' => $raw,
            'highlights' => PendingApprovalSummary::forController($controller, $raw),
            'can_approve' => $canApprove,
        ];
    }

    private function presentRaw(HrRequest $row): object
    {
        switch ($row->request_type) {
            case HrRequest::TYPE_LEAVE:
                return LeaveRequestPresenter::toGreatdayJson($row);
            case HrRequest::TYPE_PERMISSION:
                return PermissionRequestPresenter::toGreatdayJson($row);
            case HrRequest::TYPE_OVERTIME:
                return OvertimeRequestPresenter::toGreatdayJson($row);
            case HrRequest::TYPE_ATTENDANCE_CORRECTION:
                return AttendanceCorrectionPresenter::toGreatdayJson($row);
            default:
                $karyawan = MasterKaryawan::find($row->karyawan_id);

                return (object) [
                    'id' => $row->id,
                    'status' => $row->status,
                    'employee_name' => $karyawan->nama_lengkap ?? '',
                    'employee_position' => $karyawan->jabatan ?? '',
                    'description' => $row->description,
                    'no_document' => $row->no_document,
                ];
        }
    }
}
