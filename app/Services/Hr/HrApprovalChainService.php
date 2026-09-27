<?php

namespace App\Services\Hr;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\DynamicApprovalChainService;
use App\Services\Greatday\FirebaseService;

class HrApprovalChainService
{
    public function viewerCanApprove(HrRequest $request, MasterKaryawan $viewer): bool
    {
        if ($request->status !== WorkflowStatus::PENDING) {
            return false;
        }

        if ((int) $request->karyawan_id === (int) $viewer->id) {
            return false;
        }

        if (!AtasanApprovalScope::isAtasanGrade($viewer)) {
            return false;
        }

        $step = $this->currentPendingAtasanStep($request);
        if (!$step) {
            return false;
        }

        if ($step->expected_karyawan_id !== null) {
            return (int) $step->expected_karyawan_id === (int) $viewer->id;
        }

        return AtasanApprovalScope::isSubordinateKaryawan($viewer, (int) $request->karyawan_id);
    }

    public function currentPendingAtasanStep(HrRequest $request): ?HrApprovalStep
    {
        return HrApprovalStep::query()
            ->where('request_id', $request->id)
            ->where('step', HrApprovalStep::STEP_ATASAN)
            ->where('state', HrApprovalStep::STATE_PENDING)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    public function notifyCurrentApprovers(HrRequest $request, MasterKaryawan $submitter, string $urlPath): void
    {
        if ($request->status !== WorkflowStatus::PENDING) {
            return;
        }

        $step = $this->currentPendingAtasanStep($request);
        if (!$step || !$step->expected_karyawan_id) {
            return;
        }

        $service = new FirebaseService();
        $service->sendNotifications([(int) $step->expected_karyawan_id], [
            'title' => 'Pengajuan menunggu persetujuan',
            'body' => 'Pengajuan dari ' . ($submitter->nama_lengkap ?? 'karyawan') . ' menunggu persetujuan Anda',
            'url' => $urlPath,
        ]);
    }

    public function applyEmptyChainAutoAdvance(HrRequest $request): HrRequest
    {
        $hasPending = HrApprovalStep::query()
            ->where('request_id', $request->id)
            ->where('step', HrApprovalStep::STEP_ATASAN)
            ->where('state', HrApprovalStep::STATE_PENDING)
            ->exists();

        if (!$hasPending && $request->status === WorkflowStatus::PENDING) {
            $request->status = WorkflowStatus::APPROVED_ATASAN;
            $request->save();
        }

        return $request->fresh();
    }

    /** @return list<int> */
    public function previewChain(MasterKaryawan $submitter): array
    {
        return app(DynamicApprovalChainService::class)->orderedApproverIds($submitter);
    }
}
