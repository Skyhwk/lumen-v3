<?php

namespace App\Services\Hr;

use App\Models\Greatday\PermissionRequest;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\PendingApprovalSummary;
use App\Services\Hr\Concerns\LegacyFormAtasanQueueLogic;
use App\Services\Hr\Presenters\PermissionRequestPresenter;

class PermissionRequestAtasanQueueService
{
    use LegacyFormAtasanQueueLogic;

    /** @var array<int, int> */
    private $resolvedKaryawanIds = [];

    /**
     * Sama dengan AttendanceCorrectionAtasanQueueService::waitingAtasanNameForLegacyRow.
     * Baris datatable izin tidak berbentuk model legacy, jadi dinormalkan dulu.
     */
    public function waitingAtasanNameForLegacyRow(object $row): string
    {
        $legacy = $this->legacyChainRow($row);

        return $this->resolveWaitingAtasanName($legacy, HrRequest::TYPE_PERMISSION, (int) $legacy->id);
    }

    /** @return list<array<string, mixed>> */
    public function atasanStepsForLegacyRow(object $row): array
    {
        $legacy = $this->legacyChainRow($row);

        return $this->resolveAtasanSteps($legacy, HrRequest::TYPE_PERMISSION, (int) $legacy->id);
    }

    /**
     * Bentuk yang dibaca LegacyFormAtasanQueueLogic: id legacy, employee_id = master_karyawan.id, status mentah.
     */
    private function legacyChainRow(object $row): object
    {
        if (!isset($row->status_raw) && isset($row->employee_id, $row->status)) {
            $legacy = clone $row;
            $legacy->id = (int) $row->id;
            $legacy->employee_id = $this->resolveKaryawanId((int) $row->employee_id);

            return $legacy;
        }

        $karyawanId = (int) ($row->karyawan_id ?? 0);
        if ($karyawanId <= 0) {
            $karyawanId = $this->resolveKaryawanId((int) ($row->permission_employee_id ?? 0));
        }

        return (object) [
            'id' => (int) ($row->legacy_id ?? 0),
            'employee_id' => $karyawanId,
            'status' => (string) ($row->status_raw ?? ''),
            'approved_atasan_by' => $row->approved_atasan_by ?? null,
            'approved_atasan_at' => $row->approved_atasan_at ?? null,
            'rejected_atasan_by' => $row->rejected_atasan_by ?? null,
            'rejected_atasan_at' => $row->rejected_atasan_at ?? null,
            'reject_atasan_reason' => $row->reject_atasan_reason ?? null,
        ];
    }

    private function resolveKaryawanId(int $employeeId): int
    {
        if ($employeeId <= 0) {
            return 0;
        }
        if (array_key_exists($employeeId, $this->resolvedKaryawanIds)) {
            return $this->resolvedKaryawanIds[$employeeId];
        }

        $byId = MasterKaryawan::query()->where('id', $employeeId)->value('id');
        if ($byId) {
            return $this->resolvedKaryawanIds[$employeeId] = (int) $byId;
        }

        $byUserId = MasterKaryawan::query()->where('user_id', $employeeId)->value('id');

        return $this->resolvedKaryawanIds[$employeeId] = $byUserId ? (int) $byUserId : 0;
    }

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

            if (!$this->legacyRowWaitingOnApprover($row, HrRequest::TYPE_PERMISSION, $legacyId, $approverId, $approverName)) {
                continue;
            }

            $hrRequest = HrRequestResolver::findMappedFromLegacyRow(HrRequest::TYPE_PERMISSION, $legacyId);
            $canApprove = $hrRequest
                ? $chain->viewerCanApprove($hrRequest, $approver)
                : AtasanApprovalScope::isSubordinateKaryawan($approver, $submitterId);

            $items[] = $this->mapPendingItem($row, $hrRequest, $canApprove);
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, PermissionRequest> */
    private function pendingLegacyRows()
    {
        return PermissionRequest::query()
            ->where('status', WorkflowStatus::PENDING)
            ->where('is_active', true)
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
    private function mapPendingItem(PermissionRequest $legacyRow, ?HrRequest $hrRequest, bool $canApprove): array
    {
        $legacyId = (int) $legacyRow->id;
        $controller = 'PermissionRequestsController';

        if ($hrRequest) {
            $raw = PermissionRequestPresenter::toGreatdayJson($hrRequest);
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
            app(LegacyFormGreatdayApprovalSteps::class)->applyToObject($raw, HrRequest::TYPE_PERMISSION);
        }

        return [
            'id' => $legacyId,
            'controller' => $controller,
            'title' => 'Permission Request',
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
