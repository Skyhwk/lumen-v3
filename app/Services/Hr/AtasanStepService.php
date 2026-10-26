<?php

namespace App\Services\Hr;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\DynamicApprovalChainService;
use Carbon\Carbon;
use InvalidArgumentException;

class AtasanStepService
{
    /**
     * Buat rantai step atasan dari hirarki. Mengembalikan true jika masih ada step pending.
     */
    public function seedChainForSubmitter(int $requestId, MasterKaryawan $submitter): bool
    {
        HrApprovalStep::query()
            ->where('request_id', $requestId)
            ->where('step', HrApprovalStep::STEP_ATASAN)
            ->delete();

        $approverIds = app(DynamicApprovalChainService::class)->orderedApproverIds($submitter);
        $order = 0;
        foreach ($approverIds as $approverId) {
            if ((int) $approverId === (int) $submitter->id) {
                continue;
            }
            $order++;
            HrApprovalStep::create([
                'request_id' => $requestId,
                'step' => HrApprovalStep::STEP_ATASAN,
                'sort_order' => $order,
                'expected_karyawan_id' => (int) $approverId,
                'state' => HrApprovalStep::STATE_PENDING,
            ]);
        }

        return $order > 0;
    }

    /** @deprecated Gunakan seedChainForSubmitter */
    public function seedPendingAtasanStep(int $requestId): void
    {
        $submitterId = HrRequest::query()->where('id', $requestId)->value('karyawan_id');
        if (!$submitterId) {
            HrApprovalStep::create([
                'request_id' => $requestId,
                'step' => HrApprovalStep::STEP_ATASAN,
                'sort_order' => 1,
                'state' => HrApprovalStep::STATE_PENDING,
            ]);

            return;
        }

        $submitter = MasterKaryawan::find($submitterId);
        if ($submitter) {
            $this->seedChainForSubmitter($requestId, $submitter);

            return;
        }

        HrApprovalStep::create([
            'request_id' => $requestId,
            'step' => HrApprovalStep::STEP_ATASAN,
            'sort_order' => 1,
            'state' => HrApprovalStep::STATE_PENDING,
        ]);
    }

    public function markApprovedAtasan(HrRequest $request, MasterKaryawan $approver): void
    {
        $now = Carbon::now();
        $step = app(HrApprovalChainService::class)->currentPendingAtasanStep($request);

        if (!$step) {
            throw new InvalidArgumentException('Tidak ada step atasan yang menunggu persetujuan.');
        }

        if ($step->expected_karyawan_id !== null && (int) $step->expected_karyawan_id !== (int) $approver->id) {
            throw new InvalidArgumentException('Bukan giliran Anda menyetujui pengajuan ini.');
        }

        HrApprovalStep::where('id', $step->id)->update([
            'state' => HrApprovalStep::STATE_APPROVED,
            'actor_karyawan_id' => $approver->id,
            'actor_name' => $approver->nama_lengkap,
            'acted_at' => $now,
        ]);

        $next = app(HrApprovalChainService::class)->currentPendingAtasanStep($request->fresh());

        $request->updated_by_name = $approver->nama_lengkap;
        $request->updated_at = $now;

        if ($next) {
            $request->status = WorkflowStatus::PENDING;
            $request->save();
            if ($next->expected_karyawan_id) {
                $submitter = MasterKaryawan::find($request->karyawan_id);
                app(HrApprovalChainService::class)->notifyCurrentApprovers(
                    $request->fresh(),
                    $submitter ?: $approver,
                    \App\Support\Greatday\NotificationCopy::pathForms('approval')
                );
            }
        } else {
            $request->status = WorkflowStatus::APPROVED_ATASAN;
            $request->save();
        }
    }

    public function markRejectedAtasan(HrRequest $request, MasterKaryawan $approver, ?string $reason): void
    {
        $now = Carbon::now();
        $step = app(HrApprovalChainService::class)->currentPendingAtasanStep($request);

        if ($step) {
            if ($step->expected_karyawan_id !== null && (int) $step->expected_karyawan_id !== (int) $approver->id) {
                throw new InvalidArgumentException('Bukan giliran Anda menolak pengajuan ini.');
            }

            HrApprovalStep::where('id', $step->id)->update([
                'state' => HrApprovalStep::STATE_REJECTED,
                'actor_karyawan_id' => $approver->id,
                'actor_name' => $approver->nama_lengkap,
                'acted_at' => $now,
                'reason' => $reason,
            ]);
        }

        HrApprovalStep::query()
            ->where('request_id', $request->id)
            ->where('step', HrApprovalStep::STEP_ATASAN)
            ->where('state', HrApprovalStep::STATE_PENDING)
            ->update(['state' => HrApprovalStep::STATE_SKIPPED]);

        $request->status = WorkflowStatus::REJECTED_ATASAN;
        $request->updated_by_name = $approver->nama_lengkap;
        $request->updated_at = $now;
        $request->save();
    }
}
