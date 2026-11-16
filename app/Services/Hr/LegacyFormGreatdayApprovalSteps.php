<?php

namespace App\Services\Hr;

use App\Services\Hr\Concerns\LegacyFormAtasanQueueLogic;
use App\Services\Hr\Presenters\HrApprovalChainPresenter;

/** Rantai atasan legacy → format progres Greatday (nama SPV/Manager di stepper). */
final class LegacyFormGreatdayApprovalSteps
{
    use LegacyFormAtasanQueueLogic;

    /**
     * @return list<array{sort_order: int, approver_name: ?string, state: string, acted_at: ?string, reject_reason: ?string}>
     */
    public function forRow(object $legacyRow, string $requestType): array
    {
        $legacyId = (int) ($legacyRow->id ?? 0);
        if ($legacyId <= 0) {
            return [];
        }

        return HrApprovalChainPresenter::fromPortalLegacyAtasanSteps(
            $this->resolveAtasanSteps($legacyRow, $requestType, $legacyId)
        );
    }

    public function applyToObject(object $legacyRow, string $requestType): object
    {
        $legacyRow->atasan_approval_steps = $this->forRow($legacyRow, $requestType);

        return $legacyRow;
    }
}
