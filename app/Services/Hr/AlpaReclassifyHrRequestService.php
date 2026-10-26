<?php

namespace App\Services\Hr;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrLeaveDetail;
use App\Models\Hr\HrPermissionDetail;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Permohonan cuti/izin Approved HRD dari koreksi alpa (backdated, tanpa workflow mobile).
 */
class AlpaReclassifyHrRequestService
{
    public function createApproved(
        MasterKaryawan $employee,
        string $target,
        string $refYmd,
        string $description,
        ?MasterKaryawan $actor,
        ?string $actorName,
        string $permissionKind = 'event'
    ): HrRequest {
        $target = strtolower(trim($target));
        if (!in_array($target, ['izin', 'cuti'], true)) {
            throw new InvalidArgumentException('Jenis koreksi harus izin atau cuti.');
        }

        $description = trim($description);
        if ($description === '') {
            throw new InvalidArgumentException('Keterangan koreksi wajib diisi.');
        }

        $ref = Carbon::parse($refYmd)->startOfDay();
        $now = Carbon::now();
        $name = $actorName ?: ($actor ? $actor->nama_lengkap : 'HRD');
        $actorKaryawanId = $actor ? (int) $actor->id : null;

        $submittedAt = $ref->copy()->setTime(8, 0, 0);
        if ($submittedAt->gt($now)) {
            $submittedAt = $now;
        }

        $header = HrRequest::create([
            'uuid' => (string) Str::uuid(),
            'request_type' => $target === 'cuti' ? HrRequest::TYPE_LEAVE : HrRequest::TYPE_PERMISSION,
            'no_document' => 'ALPA-KR/' . str_replace('.', '/', microtime(true)),
            'karyawan_id' => $employee->id,
            'id_cabang' => $employee->id_cabang ?? null,
            'id_department' => $employee->id_department ?? null,
            'status' => WorkflowStatus::APPROVED_HRD,
            'workflow_code' => 'default_2_step',
            'description' => $description,
            'submitted_at' => $submittedAt,
            'created_by_karyawan_id' => $actorKaryawanId ?: $employee->id,
            'created_by_name' => $name,
            'created_at' => $submittedAt,
            'updated_by_name' => $name,
            'updated_at' => $now,
            'is_active' => true,
        ]);

        if ($target === 'cuti') {
            HrLeaveDetail::create([
                'request_id' => $header->id,
                'leave_kind' => 'annual',
                'special_leave_type_id' => null,
                'start_date' => $refYmd,
                'end_date' => $refYmd,
                'attachment_path' => null,
            ]);
            $header = $header->fresh(['leaveDetail']);
        } else {
            $kind = strtolower(trim($permissionKind));
            if (!in_array($kind, ['event', 'sick'], true)) {
                throw new InvalidArgumentException('Jenis izin harus event atau sick.');
            }

            HrPermissionDetail::create([
                'request_id' => $header->id,
                'permission_kind' => $kind,
                'start_date' => $refYmd,
                'end_date' => $refYmd,
                'start_time' => null,
                'end_time' => null,
                'attachment_path' => null,
            ]);
            $header = $header->fresh(['permissionDetail']);
        }

        $this->seedApprovedHrdSteps($header->id, $actor, $name, $now);

        $mirror = app(LegacyHrMirror::class);
        $mirror->mirrorCreateFromHrRequest($header);
        $mirror->syncPortalHrdDecision($header->fresh());

        return $header->fresh(['leaveDetail', 'permissionDetail']);
    }

    private function seedApprovedHrdSteps(int $requestId, ?MasterKaryawan $approver, string $actorName, Carbon $actedAt): void
    {
        HrApprovalStep::where('request_id', $requestId)->delete();

        HrApprovalStep::create([
            'request_id' => $requestId,
            'step' => HrApprovalStep::STEP_ATASAN,
            'state' => HrApprovalStep::STATE_SKIPPED,
            'actor_karyawan_id' => null,
            'actor_name' => $actorName,
            'reason' => 'Koreksi alpa HRD — atasan dilewati',
            'acted_at' => $actedAt,
        ]);

        HrApprovalStep::create([
            'request_id' => $requestId,
            'step' => HrApprovalStep::STEP_HRD,
            'state' => HrApprovalStep::STATE_APPROVED,
            'actor_karyawan_id' => $approver ? (int) $approver->id : null,
            'actor_name' => $actorName,
            'reason' => null,
            'acted_at' => $actedAt,
        ]);
    }
}
