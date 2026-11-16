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
    /**
     * @param list<array{state?: string, actor?: ?string, acted_at?: ?string, note?: ?string}> $portalSteps
     * @return list<array{sort_order: int, approver_name: ?string, state: string, acted_at: ?string, reject_reason: ?string}>
     */
    public static function fromPortalLegacyAtasanSteps(array $portalSteps): array
    {
        $out = [];
        foreach ($portalSteps as $index => $step) {
            $portalState = (string) ($step['state'] ?? 'waiting');
            $state = HrApprovalStep::STATE_PENDING;
            if ($portalState === 'done') {
                $state = HrApprovalStep::STATE_APPROVED;
            } elseif ($portalState === 'rejected') {
                $state = HrApprovalStep::STATE_REJECTED;
            }

            $actor = $step['actor'] ?? null;
            $name = is_string($actor) && trim($actor) !== '' ? trim($actor) : null;

            $out[] = [
                'sort_order' => $index + 1,
                'approver_name' => $name,
                'state' => $state,
                'acted_at' => isset($step['acted_at']) && $step['acted_at'] !== '' ? (string) $step['acted_at'] : null,
                'reject_reason' => isset($step['note']) && $step['note'] !== '' ? (string) $step['note'] : null,
            ];
        }

        return $out;
    }

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

    /**
     * Progres atasan untuk Super Apps (state: done | active | waiting | rejected).
     *
     * @return list<array{key: string, title: string, state: string, actor: ?string, acted_at: ?string, note: ?string}>
     */
    public static function atasanStepsForPortal(HrRequest $request): array
    {
        $request->loadMissing('approvalSteps');

        $steps = $request->approvalSteps
            ->where('step', HrApprovalStep::STEP_ATASAN)
            ->sortBy('sort_order')
            ->values();

        if ($steps->isEmpty()) {
            return [];
        }

        $total = $steps->count();
        $foundActive = false;
        $out = [];

        foreach ($steps as $index => $step) {
            $name = $step->actor_name;
            if (($name === null || $name === '') && $step->expected_karyawan_id) {
                $name = MasterKaryawan::query()
                    ->where('id', $step->expected_karyawan_id)
                    ->value('nama_lengkap');
            }

            $state = 'waiting';
            $actedAt = $step->acted_at ? (string) $step->acted_at : null;
            $note = null;

            if ($step->state === HrApprovalStep::STATE_APPROVED) {
                $state = 'done';
            } elseif ($step->state === HrApprovalStep::STATE_REJECTED) {
                $state = 'rejected';
                $note = $step->reason;
            } elseif ($step->state === HrApprovalStep::STATE_PENDING && !$foundActive) {
                $state = 'active';
                $foundActive = true;
                $actedAt = null;
            } elseif ($step->state === HrApprovalStep::STATE_SKIPPED) {
                $state = 'done';
            }

            $out[] = [
                'key' => 'atasan-' . ($index + 1),
                'title' => $total > 1 ? 'Persetujuan atasan ' . ($index + 1) : 'Persetujuan Atasan',
                'state' => $state,
                'actor' => $name,
                'acted_at' => $actedAt,
                'note' => $note,
            ];
        }

        return $out;
    }
}
