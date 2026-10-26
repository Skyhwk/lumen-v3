<?php

namespace App\Services\Hr;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Satu pintu approval workflow — channel greatday hanya step atasan.
 */
class ApprovalService
{
    public const CHANNEL_GREATDAY = 'greatday';

    public const CHANNEL_PORTAL = 'portal';

    public function assertChannelMayActOnStep(string $channel, string $step): void
    {
        if ($channel === self::CHANNEL_GREATDAY && $step !== HrApprovalStep::STEP_ATASAN) {
            throw new InvalidArgumentException(
                'Greatday hanya boleh aksi step atasan. HRD/finance/konsultasi diproses di portal V3.'
            );
        }
    }

    public function approveAtasan(HrRequest $request, MasterKaryawan $approver, string $channel = self::CHANNEL_GREATDAY): void
    {
        $this->assertChannelMayActOnStep($channel, HrApprovalStep::STEP_ATASAN);
        app(AtasanStepService::class)->markApprovedAtasan($request, $approver);
    }

    public function rejectAtasan(HrRequest $request, MasterKaryawan $approver, ?string $reason, string $channel = self::CHANNEL_GREATDAY): void
    {
        $this->assertChannelMayActOnStep($channel, HrApprovalStep::STEP_ATASAN);
        app(AtasanStepService::class)->markRejectedAtasan($request, $approver, $reason);
    }

    public function approveHrd(HrRequest $request, MasterKaryawan $approver, ?string $actorName = null): void
    {
        $this->assertChannelMayActOnStep(self::CHANNEL_PORTAL, HrApprovalStep::STEP_HRD);
        $now = Carbon::now();
        $name = $actorName ?: $approver->nama_lengkap;

        $request->status = WorkflowStatus::APPROVED_HRD;
        $request->updated_by_name = $name;
        $request->updated_at = $now;
        $request->save();

        $this->recordPortalStep($request->id, HrApprovalStep::STEP_HRD, HrApprovalStep::STATE_APPROVED, $approver, null, $now, $name);
    }

    public function rejectHrd(HrRequest $request, MasterKaryawan $approver, ?string $reason, ?string $actorName = null): void
    {
        $this->assertChannelMayActOnStep(self::CHANNEL_PORTAL, HrApprovalStep::STEP_HRD);
        $now = Carbon::now();
        $name = $actorName ?: $approver->nama_lengkap;

        $request->status = WorkflowStatus::REJECTED_HRD;
        $request->updated_by_name = $name;
        $request->updated_at = $now;
        $request->save();

        $this->recordPortalStep($request->id, HrApprovalStep::STEP_HRD, HrApprovalStep::STATE_REJECTED, $approver, $reason, $now, $name);
    }

    private function recordPortalStep(
        int $requestId,
        string $step,
        string $state,
        ?MasterKaryawan $approver,
        ?string $reason,
        Carbon $actedAt,
        string $actorName
    ): void {
        HrApprovalStep::where('request_id', $requestId)->where('step', $step)->delete();

        HrApprovalStep::create([
            'request_id' => $requestId,
            'step' => $step,
            'state' => $state,
            'actor_karyawan_id' => $approver ? $approver->id : null,
            'actor_name' => $actorName,
            'acted_at' => $actedAt,
            'reason' => $reason,
        ]);
    }
}
