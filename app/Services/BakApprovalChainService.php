<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Services\Greatday\DynamicApprovalChainService;

/**
 * Rantai verifikasi atasan khusus BAK: hirarki penandatangan tanpa SPV (Manager → Senior Manager saja).
 */
final class BakApprovalChainService
{
    /**
     * @return list<int>
     */
    public function orderedApproverIds(MasterKaryawan $penandatangan): array
    {
        $ids = app(DynamicApprovalChainService::class)->orderedApproverIds($penandatangan);

        $filtered = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && $this->isManagerOrSeniorManager($id)) {
                $filtered[] = $id;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function isManagerOrSeniorManager(int $karyawanId): bool
    {
        $grade = MasterKaryawan::query()
            ->where('id', $karyawanId)
            ->where('is_active', true)
            ->value('grade');

        $normalized = $this->normalizeGrade($grade);

        return $normalized === 'MANAGER' || $normalized === 'SENIOR MANAGER';
    }

    private function normalizeGrade(?string $grade): string
    {
        $g = strtoupper(trim(str_replace('_', ' ', (string) $grade)));
        if ($g === 'SPV') {
            return 'SUPERVISOR';
        }

        return $g;
    }
}