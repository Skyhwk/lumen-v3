<?php

namespace App\Services\Hr\Greatday;

use App\Models\Greatday\AttendanceCorrection;
use App\Models\Greatday\LeaveRequest;
use App\Models\Greatday\PermissionRequest;
use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\PortalHrSync;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use InvalidArgumentException;

class FormSubmitterVoidService
{
    public function assertCanVoidHr(HrRequest $request, MasterKaryawan $submitter): ?string
    {
        if ((int) $request->karyawan_id !== (int) $submitter->id) {
            return 'Hanya pengaju yang dapat membatalkan pengajuan ini.';
        }

        if (!$request->is_active) {
            return 'Pengajuan sudah tidak aktif.';
        }

        if ($request->status !== WorkflowStatus::PENDING) {
            return 'Pengajuan hanya dapat dibatalkan sebelum disetujui atasan.';
        }

        $request->loadMissing('approvalSteps');
        foreach ($request->approvalSteps->where('step', HrApprovalStep::STEP_ATASAN) as $step) {
            if ($step->state === HrApprovalStep::STATE_APPROVED) {
                return 'Pengajuan sudah disetujui atasan, tidak dapat dibatalkan.';
            }
        }

        return null;
    }

    public function voidHrRequest(HrRequest $request, MasterKaryawan $submitter): void
    {
        $message = $this->assertCanVoidHr($request, $submitter);
        if ($message !== null) {
            throw new InvalidArgumentException($message);
        }

        $now = Carbon::now();
        $request->is_active = false;
        $request->updated_by_name = $submitter->nama_lengkap;
        $request->updated_at = $now;
        $request->save();

        app(LegacyHrMirror::class)->syncVoid($request->fresh());
    }

    public function voidLegacyLeave(int $legacyId, MasterKaryawan $submitter): void
    {
        $row = LeaveRequest::query()->where('id', $legacyId)->where('is_active', true)->first();
        if (!$row) {
            throw new InvalidArgumentException('Permohonan cuti tidak ditemukan.');
        }
        $this->voidLegacyRow($row, (int) $row->employee_id, $submitter, fn () => app(PortalHrSync::class)->syncLeaveFromLegacy($legacyId));
    }

    public function voidLegacyPermission(int $legacyId, MasterKaryawan $submitter): void
    {
        $row = PermissionRequest::query()->where('id', $legacyId)->where('is_active', true)->first();
        if (!$row) {
            throw new InvalidArgumentException('Permohonan izin tidak ditemukan.');
        }
        $this->voidLegacyRow($row, (int) $row->employee_id, $submitter, fn () => app(PortalHrSync::class)->syncPermissionFromLegacy($legacyId));
    }

    public function voidLegacyAttendanceCorrection(int $legacyId, MasterKaryawan $submitter): void
    {
        $row = AttendanceCorrection::query()->where('id', $legacyId)->where('is_active', true)->first();
        if (!$row) {
            throw new InvalidArgumentException('Koreksi absensi tidak ditemukan.');
        }
        $this->voidLegacyRow($row, (int) $row->employee_id, $submitter, function () use ($legacyId) {
            $hr = HrRequestResolver::findByApiId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $legacyId);
            if ($hr) {
                $hr->is_active = false;
                $hr->save();
            }
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $row
     */
    private function voidLegacyRow($row, int $ownerId, MasterKaryawan $submitter, callable $afterSave): void
    {
        if ($ownerId !== (int) $submitter->id) {
            throw new InvalidArgumentException('Hanya pengaju yang dapat membatalkan pengajuan ini.');
        }

        if (($row->status ?? '') !== WorkflowStatus::PENDING) {
            throw new InvalidArgumentException('Pengajuan hanya dapat dibatalkan sebelum disetujui atasan.');
        }

        if (!empty($row->approved_atasan_by)) {
            throw new InvalidArgumentException('Pengajuan sudah disetujui atasan, tidak dapat dibatalkan.');
        }

        $row->is_active = false;
        $row->updated_by = $submitter->nama_lengkap;
        $row->updated_at = date('Y-m-d H:i:s');
        $row->save();

        $afterSave();
    }
}
