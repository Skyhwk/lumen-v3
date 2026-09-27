<?php

namespace App\Services\Hr\Greatday\Concerns;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Hr\AtasanStepService;
use App\Services\Hr\HrApprovalChainService;
use App\Services\Hr\WorkflowStatus;

trait BootstrapsHrAtasanChain
{
    protected function bootstrapAtasanChain(HrRequest $header, MasterKaryawan $employee, string $notifyUrl): HrRequest
    {
        $hasChain = app(AtasanStepService::class)->seedChainForSubmitter($header->id, $employee);
        $header = $header->fresh();

        if (!$hasChain) {
            $header->status = WorkflowStatus::APPROVED_ATASAN;
            $header->save();
        }

        app(HrApprovalChainService::class)->notifyCurrentApprovers($header->fresh(), $employee, $notifyUrl);

        return $header->fresh();
    }
}
