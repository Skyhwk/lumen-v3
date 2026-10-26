<?php

namespace App\Services\Hr\Greatday\Concerns;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\FirebaseService;
use App\Services\Hr\AtasanStepService;
use App\Services\Hr\HrApprovalChainService;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\NotificationCopy;

trait BootstrapsHrAtasanChain
{
    protected function bootstrapAtasanChain(HrRequest $header, MasterKaryawan $employee): HrRequest
    {
        $hasChain = app(AtasanStepService::class)->seedChainForSubmitter($header->id, $employee);
        $header = $header->fresh();

        if (!$hasChain) {
            $header->status = WorkflowStatus::APPROVED_ATASAN;
            $header->save();
        }

        $fresh = $header->fresh();
        try {
            app(HrApprovalChainService::class)->notifyCurrentApprovers(
                $fresh,
                $employee,
                NotificationCopy::pathForms('approval')
            );

            (new FirebaseService())->sendNotifications(
                [(int) $employee->id],
                NotificationCopy::submissionAcknowledged(
                    $fresh,
                    NotificationCopy::pathForms('submission'),
                    $hasChain
                )
            );
        } catch (\Throwable $e) {
            // Notifikasi tidak boleh gagalkan simpan pengajuan HR.
        }

        return $fresh;
    }
}
