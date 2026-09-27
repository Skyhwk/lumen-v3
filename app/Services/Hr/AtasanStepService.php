<?php

namespace App\Services\Hr;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use Carbon\Carbon;

class AtasanStepService
{
    public function markApprovedAtasan(HrRequest $request, MasterKaryawan $approver): void
    {
        $now = Carbon::now();

        $request->status = WorkflowStatus::APPROVED_ATASAN;
        $request->updated_by_name = $approver->nama_lengkap;
        $request->updated_at = $now;
        $request->save();

        $this->recordStep($request->id, HrApprovalStep::STEP_ATASAN, HrApprovalStep::STATE_APPROVED, $approver, null, $now);
    }

    public function markRejectedAtasan(HrRequest $request, MasterKaryawan $approver, ?string $reason): void
    {
        $now = Carbon::now();

        $request->status = WorkflowStatus::REJECTED_ATASAN;
        $request->updated_by_name = $approver->nama_lengkap;
        $request->updated_at = $now;
        $request->save();

        $this->recordStep($request->id, HrApprovalStep::STEP_ATASAN, HrApprovalStep::STATE_REJECTED, $approver, $reason, $now);
    }

    public function seedPendingAtasanStep(int $requestId): void
    {
        HrApprovalStep::create([
            'request_id' => $requestId,
            'step' => HrApprovalStep::STEP_ATASAN,
            'state' => HrApprovalStep::STATE_PENDING,
        ]);
    }

    private function recordStep(
        int $requestId,
        string $step,
        string $state,
        MasterKaryawan $approver,
        ?string $reason,
        Carbon $actedAt
    ): void {
        HrApprovalStep::where('request_id', $requestId)
            ->where('step', $step)
            ->delete();

        HrApprovalStep::create([
            'request_id' => $requestId,
            'step' => $step,
            'state' => $state,
            'actor_karyawan_id' => $approver->id,
            'actor_name' => $approver->nama_lengkap,
            'acted_at' => $actedAt,
            'reason' => $reason,
        ]);
    }
}
