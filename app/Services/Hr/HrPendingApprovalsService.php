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

        $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($approver);
        $subordinateNames = AtasanApprovalScope::subordinateKaryawanNames($approver);

        if (empty($subordinateIds) && empty($subordinateNames)) {
            return [];
        }

        $pending = [];

        $leavePermissionCorrection = HrRequest::with(['leaveDetail', 'permissionDetail', 'attendanceCorrectionDetail'])
            ->where('status', WorkflowStatus::PENDING)
            ->whereIn('request_type', [
                HrRequest::TYPE_LEAVE,
                HrRequest::TYPE_PERMISSION,
                HrRequest::TYPE_ATTENDANCE_CORRECTION,
            ])
            ->where(function ($q) use ($subordinateIds) {
                if (!empty($subordinateIds)) {
                    $q->whereIn('karyawan_id', $subordinateIds);
                } else {
                    $q->whereRaw('0 = 1');
                }
            })
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        foreach ($leavePermissionCorrection as $row) {
            $pending[] = $this->mapRow($row);
        }

        $overtimeRows = HrRequest::with(['overtimeDetail', 'overtimeParticipants'])
            ->where('request_type', HrRequest::TYPE_OVERTIME)
            ->where('status', WorkflowStatus::PENDING)
            ->where('is_active', true)
            ->where(function ($q) use ($subordinateIds, $subordinateNames) {
                $q->where(function ($inner) use ($subordinateIds, $subordinateNames) {
                    if (!empty($subordinateIds)) {
                        $inner->whereIn('karyawan_id', $subordinateIds)
                            ->orWhereHas('overtimeParticipants', fn ($p) => $p->whereIn('karyawan_id', $subordinateIds));
                    }
                    if (!empty($subordinateNames)) {
                        $inner->orWhereIn('created_by_name', $subordinateNames);
                    }
                    if (empty($subordinateIds) && empty($subordinateNames)) {
                        $inner->whereRaw('0 = 1');
                    }
                });
            })
            ->orderByDesc('id')
            ->get();

        foreach ($overtimeRows as $row) {
            $pending[] = $this->mapRow($row);
        }

        return $pending;
    }

    private function mapRow(HrRequest $row): array
    {
        $meta = self::TYPE_META[$row->request_type] ?? ['title' => 'HR Request', 'controller' => 'HrRequestController'];
        $karyawan = MasterKaryawan::find($row->karyawan_id);
        $raw = $this->presentRaw($row);
        $controller = $meta['controller'];

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
            'can_approve' => true,
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
