<?php

namespace App\Services\Hr;

use App\Models\MasterKaryawan;

/**
 * Penerima notifikasi submit formulir HR — selaras hr_approval_step / kebijakan Sampler cabang.
 */
final class FormAtasanSubmitNotificationService
{
    /**
     * Approver pertama yang harus diberi tahu (satu orang per giliran).
     *
     * @return list<int>
     */
    public function initialApproverIds(MasterKaryawan $submitter, ?string $requestType): array
    {
        $chain = app(FormAtasanApprovalChainService::class)->orderedApproverIds($submitter, $requestType);
        if ($chain === []) {
            return [];
        }

        $first = (int) $chain[0];

        return $first > 0 ? [$first] : [];
    }
}
