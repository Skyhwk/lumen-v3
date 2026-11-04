<?php

namespace App\Services\Hr;

use App\Models\Hr\HrRequest;
use App\Models\IntilabInternal\AttendanceCorrections;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\PendingApprovalSummary;
use App\Services\Hr\Concerns\LegacyFormAtasanQueueLogic;
use App\Services\Hr\Presenters\AttendanceCorrectionPresenter;

/**
 * Antrian koreksi absen menunggu atasan — selaras kolom "Menunggu Atasan" Super Apps (Attendance).
 */
class AttendanceCorrectionAtasanQueueService
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

            if (!$this->legacyRowWaitingOnApprover($row, HrRequest::TYPE_ATTENDANCE_CORRECTION, $legacyId, $approverId, $approverName)) {
                continue;
            }

            $hrRequest = HrRequestResolver::findMappedFromLegacyRow(HrRequest::TYPE_ATTENDANCE_CORRECTION, $legacyId);
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
            $this->pendingLegacyRows(),
            HrRequest::TYPE_ATTENDANCE_CORRECTION
        );
    }

    public function waitingAtasanNameForLegacyRow(AttendanceCorrections $row): string
    {
        return $this->resolveWaitingAtasanName($row, HrRequest::TYPE_ATTENDANCE_CORRECTION, (int) $row->id);
    }

    /** @return list<array<string, mixed>> */
    public function atasanStepsForLegacyRow(AttendanceCorrections $row): array
    {
        return $this->resolveAtasanSteps($row, HrRequest::TYPE_ATTENDANCE_CORRECTION, (int) $row->id);
    }

    /** @return \Illuminate\Support\Collection<int, AttendanceCorrections> */
    private function pendingLegacyRows()
    {
        return AttendanceCorrections::query()
            ->where('is_active', 1)
            ->where('status', WorkflowStatus::PENDING)
            ->whereNotNull('employee_id')
            ->with(['karyawan.jabatan'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPendingItem(AttendanceCorrections $legacyRow, ?HrRequest $hrRequest, bool $canApprove): array
    {
        $legacyId = (int) $legacyRow->id;
        $controller = 'AttendanceCorrectionsController';

        if ($hrRequest) {
            $raw = AttendanceCorrectionPresenter::toGreatdayJson($hrRequest);
            $raw->id = $legacyId;
        } else {
            $karyawan = $legacyRow->karyawan ?? $this->legacySubmitterEmployee($legacyRow);
            $raw = (object) [
                'id' => $legacyId,
                'employee_id' => (int) $legacyRow->employee_id,
                'type' => $legacyRow->type ?? null,
                'date' => $legacyRow->date ?? null,
                'time' => $legacyRow->time ?? null,
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
            app(LegacyFormGreatdayApprovalSteps::class)->applyToObject($raw, HrRequest::TYPE_ATTENDANCE_CORRECTION);
        }

        return [
            'id' => $legacyId,
            'controller' => $controller,
            'title' => 'Attendance Correction',
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
