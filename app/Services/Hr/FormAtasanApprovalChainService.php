<?php

namespace App\Services\Hr;

use App\Models\MasterKaryawan;
use App\Services\Greatday\DynamicApprovalChainService;

/**
 * Rantai approver untuk formulir HR — dynamic chain + kebijakan Sampler cabang (Fase 1/2).
 */
final class FormAtasanApprovalChainService
{
    /**
     * @return list<int>
     */
    public function orderedApproverIds(MasterKaryawan $submitter, ?string $requestType): array
    {
        $policy = app(SamplerBranchApprovalPolicy::class);
        $dac = app(DynamicApprovalChainService::class);

        if ($policy->appliesTo($submitter, $requestType)) {
            $roots = $policy->directAtasanIdsForTechnicalChain($submitter);
            $raw = $roots !== []
                ? $dac->orderedApproverIds($submitter, $roots)
                : [];
        } else {
            $raw = $dac->orderedApproverIds($submitter);
        }

        return $policy->filterApproverIds($submitter, $raw, $requestType);
    }
}
