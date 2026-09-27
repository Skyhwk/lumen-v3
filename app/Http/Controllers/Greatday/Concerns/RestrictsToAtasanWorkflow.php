<?php

namespace App\Http\Controllers\Greatday\Concerns;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use Illuminate\Http\JsonResponse;

/**
 * Greatday hanya pengajuan + approve/reject step atasan (status Pending).
 */
trait RestrictsToAtasanWorkflow
{
    protected function portalOnlyResponse(string $action = 'approve'): JsonResponse
    {
        return response()->json([
            'message' => 'Langkah ini diproses di portal V3. Greatday hanya untuk pengajuan dan persetujuan atasan (Pending).',
            'action' => $action,
        ], 403);
    }

    protected function assertPendingForAtasan(?string $status): ?JsonResponse
    {
        if ($status !== 'Pending') {
            return $this->portalOnlyResponse('approve');
        }

        return null;
    }

    protected function assertPendingForAtasanReject(?string $status): ?JsonResponse
    {
        if ($status !== 'Pending') {
            return $this->portalOnlyResponse('reject');
        }

        return null;
    }

    /**
     * Manager/Supervisor: bawahan langsung. SPV/Staff: tidak boleh approve orang lain (kecuali nanti rule khusus).
     */
    protected function assertApproverIsAtasanOf(int $employeeId): ?JsonResponse
    {
        $approver = $this->karyawan;
        if (!$approver) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ((int) $employeeId === (int) $approver->id) {
            return response()->json(['message' => 'Tidak dapat menyetujui pengajuan sendiri'], 403);
        }

        if (!AtasanApprovalScope::isAtasanGrade($approver)) {
            return response()->json(['message' => 'Hanya atasan (Manager/Supervisor) yang dapat menyetujui di Greatday'], 403);
        }

        if (!AtasanApprovalScope::isSubordinateKaryawan($approver, $employeeId)) {
            return response()->json(['message' => 'Pengajuan bukan dari bawahan Anda'], 403);
        }

        return null;
    }

    protected function assertOvertimeAtasanCanAct($overtimeRequest): ?JsonResponse
    {
        if ($deny = $this->assertPendingForAtasan($overtimeRequest->status ?? null)) {
            return $deny;
        }

        if (!AtasanApprovalScope::isAtasanGrade($this->karyawan)) {
            return response()->json(['message' => 'Hanya atasan (Manager/Supervisor) yang dapat menyetujui di Greatday'], 403);
        }

        $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($this->karyawan);
        $subordinateNames = AtasanApprovalScope::subordinateKaryawanNames($this->karyawan);

        if (empty($subordinateIds) && empty($subordinateNames)) {
            return response()->json(['message' => 'Tidak ada bawahan untuk disetujui'], 403);
        }

        $memberIds = $overtimeRequest->members()
            ->where('is_active', true)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $createdBy = (string) ($overtimeRequest->created_by ?? '');

        $allowed = (!empty($subordinateIds) && !empty(array_intersect($subordinateIds, $memberIds)))
            || ($createdBy !== '' && in_array($createdBy, $subordinateNames, true));

        if (!$allowed) {
            return response()->json(['message' => 'Tim lembur bukan dari bawahan Anda'], 403);
        }

        return null;
    }

    protected function assertOvertimeHrAtasanCanAct(HrRequest $overtimeRequest): ?JsonResponse
    {
        if ($deny = $this->assertPendingForAtasan($overtimeRequest->status ?? null)) {
            return $deny;
        }

        if (!AtasanApprovalScope::isAtasanGrade($this->karyawan)) {
            return response()->json(['message' => 'Hanya atasan (Manager/Supervisor) yang dapat menyetujui di Greatday'], 403);
        }

        $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($this->karyawan);
        $subordinateNames = AtasanApprovalScope::subordinateKaryawanNames($this->karyawan);

        if (empty($subordinateIds) && empty($subordinateNames)) {
            return response()->json(['message' => 'Tidak ada bawahan untuk disetujui'], 403);
        }

        $memberIds = $overtimeRequest->overtimeParticipants
            ->where('is_active', true)
            ->pluck('karyawan_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $createdBy = (string) ($overtimeRequest->created_by_name ?? '');

        $allowed = (!empty($subordinateIds) && !empty(array_intersect($subordinateIds, $memberIds)))
            || in_array((int) $overtimeRequest->karyawan_id, $subordinateIds, true)
            || ($createdBy !== '' && in_array($createdBy, $subordinateNames, true));

        if (!$allowed) {
            return response()->json(['message' => 'Tim lembur bukan dari bawahan Anda'], 403);
        }

        return null;
    }
}
