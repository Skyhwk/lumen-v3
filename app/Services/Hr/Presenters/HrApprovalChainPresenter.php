<?php

namespace App\Services\Hr\Presenters;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;

/** Rantai step atasan untuk UI progres Greatday (multi SPV → Manager). */
final class HrApprovalChainPresenter
{
    /**
     * @return list<array{sort_order: int, approver_name: ?string, state: string, acted_at: ?string}>
     */
    public static function atasanStepsForGreatday(HrRequest $request): array
    {
        $request->loadMissing('approvalSteps');

        $steps = $request->approvalSteps
            ->where('step', HrApprovalStep::STEP_ATASAN)
            ->sortBy('sort_order')
            ->values();

        if ($steps->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($steps as $step) {
            $name = $step->actor_name;
            if (($name === null || $name === '') && $step->expected_karyawan_id) {
                $name = MasterKaryawan::query()
                    ->where('id', $step->expected_karyawan_id)
                    ->value('nama_lengkap');
            }

            $actedAt = $step->acted_at;
            $out[] = [
                'sort_order' => (int) $step->sort_order,
                'approver_name' => $name,
                'state' => (string) ($step->state ?? HrApprovalStep::STATE_PENDING),
                'acted_at' => $actedAt ? (string) $actedAt : null,
                'reject_reason' => $step->state === HrApprovalStep::STATE_REJECTED ? ($step->reason ?? null) : null,
            ];
        }

        return $out;
    }

    /**
     * Field legacy Greatday untuk alasan / penolak (dari hr_approval_step).
     *
     * @return array<string, mixed>
     */
    public static function workflowRejectionFields(HrRequest $request): array
    {
        $request->loadMissing('approvalSteps');

        $fields = [
            'rejected_atasan_by' => null,
            'reject_atasan_reason' => null,
            'rejected_atasan_at' => null,
            'rejected_hrd_by' => null,
            'reject_hrd_reason' => null,
            'rejected_hrd_at' => null,
            'rejected_finance_by' => null,
            'reject_finance_reason' => null,
            'rejected_finance_at' => null,
        ];

        $map = [
            HrApprovalStep::STEP_ATASAN => ['rejected_atasan_by', 'reject_atasan_reason', 'rejected_atasan_at'],
            HrApprovalStep::STEP_HRD => ['rejected_hrd_by', 'reject_hrd_reason', 'rejected_hrd_at'],
            HrApprovalStep::STEP_FINANCE => ['rejected_finance_by', 'reject_finance_reason', 'rejected_finance_at'],
        ];

        foreach ($map as $stepKey => [$byKey, $reasonKey, $atKey]) {
            $row = $request->approvalSteps
                ->where('step', $stepKey)
                ->where('state', HrApprovalStep::STATE_REJECTED)
                ->sortByDesc('acted_at')
                ->first();

            if (!$row) {
                continue;
            }

            $fields[$byKey] = $row->actor_name;
            $fields[$reasonKey] = $row->reason;
            $fields[$atKey] = $row->acted_at ? (string) $row->acted_at : null;
        }

        return $fields;
    }
}
