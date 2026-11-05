<?php

namespace App\Services\Hr;

use App\Models\Greatday\LeaveRequest as GreatdayLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\PendingApprovalSummary;
use App\Services\Hr\Concerns\LegacyFormAtasanQueueLogic;
use App\Services\Hr\Presenters\LeaveRequestPresenter;
use App\Services\Hr\WorkflowStatus;

class LeaveRequestAtasanQueueService
{
    use LegacyFormAtasanQueueLogic;

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingItemsForAtasan(MasterKaryawan $approver): array
    {
        if (!AtasanApprovalScope::isAtasanGrade($approver)) {
            return [];
        }

        $approverId = (int) $approver->id;
        $approverName = trim((string) ($approver->nama_lengkap ?? ''));
        $chain = app(HrApprovalChainService::class);
        $items = [];

        foreach ($this->pendingLegacyRows() as $row) {
            $legacyId = (int) $row->id;
            $submitterId = $this->legacySubmitterEmployeeId($row);
            if ($submitterId === null || $submitterId === $approverId) {
                continue;
            }

            if (!$this->legacyRowWaitingOnApprover($row, HrRequest::TYPE_LEAVE, $legacyId, $approverId, $approverName)) {
                continue;
            }

            $hrRequest = HrRequestResolver::findMappedFromLegacyRow(HrRequest::TYPE_LEAVE, $legacyId);
            $canApprove = $hrRequest
                ? $chain->viewerCanApprove($hrRequest, $approver)
                : AtasanApprovalScope::isSubordinateKaryawan($approver, $submitterId);

            $items[] = $this->mapPendingItem($row, $hrRequest, $canApprove);
        }

        return $items;
    }

    /** @return list<int> */
    public function employeeIdsWaitingOnAtasanNamed(string $keyword): array
    {
        return $this->resolveEmployeeIdsWaitingOnAtasanNamed(
            $keyword,
            $this->hrdPendingLegacyRows(),
            HrRequest::TYPE_LEAVE
        );
    }

    public function waitingAtasanNameForLegacyRow(object $row): string
    {
        $legacyId = $this->legacyApiIdFromRow($row);
        if ($legacyId <= 0) {
            return '-';
        }

        return $this->resolveWaitingAtasanName($row, HrRequest::TYPE_LEAVE, $legacyId);
    }

    /** @return list<array<string, mixed>> */
    public function atasanStepsForLegacyRow(object $row): array
    {
        $legacyId = $this->legacyApiIdFromRow($row);
        if ($legacyId <= 0) {
            return [];
        }

        return $this->resolveAtasanSteps($row, HrRequest::TYPE_LEAVE, $legacyId);
    }

    private function legacyApiIdFromRow(object $row): int
    {
        if (isset($row->id) && (int) $row->id > 0) {
            return (int) $row->id;
        }
        if (isset($row->hr_request_id) && (int) $row->hr_request_id > 0) {
            return (int) $row->hr_request_id;
        }

        return 0;
    }

    /** @return \Illuminate\Support\Collection<int, GreatdayLeaveRequest> */
    private function pendingLegacyRows()
    {
        return GreatdayLeaveRequest::query()
            ->where('status', WorkflowStatus::PENDING)
            ->where('is_active', true)
            ->whereNotNull('employee_id')
            ->whereNull('rejected_atasan_by')
            ->whereNull('rejected_hrd_by')
            ->whereNull('approved_hrd_by')
            ->orderByDesc('id')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, LeaveRequest> */
    private function hrdPendingLegacyRows()
    {
        return LeaveRequest::on('intilab_apps')
            ->where('status', WorkflowStatus::PENDING)
            ->where('is_active', 1)
            ->whereNotNull('employee_id')
            ->whereNull('rejected_atasan_by')
            ->whereNull('rejected_hrd_by')
            ->whereNull('approved_hrd_by')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPendingItem(GreatdayLeaveRequest $legacyRow, ?HrRequest $hrRequest, bool $canApprove): array
    {
        $legacyId = (int) $legacyRow->id;
        $controller = 'LeaveRequestsController';

        if ($hrRequest) {
            $raw = LeaveRequestPresenter::toGreatdayJson($hrRequest);
            $raw->id = $legacyId;
            $raw->employee_id = (int) $legacyRow->employee_id;
        } else {
            $karyawan = $this->legacySubmitterEmployee($legacyRow);
            $raw = (object) [
                'id' => $legacyId,
                'employee_id' => (int) $legacyRow->employee_id,
                'no_document' => $legacyRow->no_document ?? null,
                'type' => $legacyRow->type ?? null,
                'start_date' => $legacyRow->start_date ?? null,
                'end_date' => $legacyRow->end_date ?? null,
                'description' => $legacyRow->description ?? '',
                'status' => $legacyRow->status,
                'employee_name' => $karyawan->nama_lengkap ?? '',
                'employee_position' => $karyawan->jabatan ?? '',
                'approved_atasan_by' => $legacyRow->approved_atasan_by ?? null,
                'approved_atasan_at' => $legacyRow->approved_atasan_at ?? null,
                'rejected_atasan_by' => $legacyRow->rejected_atasan_by ?? null,
                'rejected_atasan_at' => $legacyRow->rejected_atasan_at ?? null,
                'reject_atasan_reason' => $legacyRow->reject_atasan_reason ?? null,
            ];
            app(LegacyFormGreatdayApprovalSteps::class)->applyToObject($raw, HrRequest::TYPE_LEAVE);
        }

        return [
            'id' => $legacyId,
            'controller' => $controller,
            'title' => 'Leave Request',
            'name' => ($raw->employee_name ?? '') !== '' ? $raw->employee_name : 'Unknown',
            'position' => $raw->employee_position ?? '',
            'description' => $raw->description ?? ($legacyRow->type ?? ''),
            'status' => WorkflowStatus::PENDING,
            'raw' => $raw,
            'highlights' => PendingApprovalSummary::forController($controller, $raw),
            'can_approve' => $canApprove,
        ];
    }
}
