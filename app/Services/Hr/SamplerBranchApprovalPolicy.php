<?php

namespace App\Services\Hr;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Support\Greatday\KaryawanPresentation;

/**
 * Sampler cabang: untuk pengajuan baru cuti/izin/koreksi absen,
 * abaikan SPV operasional di cabang yang sama; pertahankan HO (dan atasan di atasnya).
 */
final class SamplerBranchApprovalPolicy
{
    /** @var list<string> */
    private const SCOPED_REQUEST_TYPES = [
        HrRequest::TYPE_LEAVE,
        HrRequest::TYPE_PERMISSION,
        HrRequest::TYPE_ATTENDANCE_CORRECTION,
    ];

    public function isEnabled(): bool
    {
        return (bool) config('greatday.sampler_branch_approval_filter', false);
    }

    public function appliesTo(MasterKaryawan $submitter, ?string $requestType): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if ($requestType === null || !in_array($requestType, self::SCOPED_REQUEST_TYPES, true)) {
            return false;
        }

        if (!$this->isSampler($submitter)) {
            return false;
        }

        $hoCabangId = $this->hoCabangId();
        $submitterCabang = (int) ($submitter->id_cabang ?? 0);

        return $submitterCabang > 0 && $submitterCabang !== $hoCabangId;
    }

    /**
     * @param list<int> $approverIds urutan dari DynamicApprovalChainService
     * @return list<int>
     */
    public function filterApproverIds(MasterKaryawan $submitter, array $approverIds, ?string $requestType): array
    {
        if (!$this->appliesTo($submitter, $requestType) || $approverIds === []) {
            return $approverIds;
        }

        $approvers = MasterKaryawan::query()
            ->whereIn('id', $approverIds)
            ->where('is_active', 1)
            ->get(['id', 'id_cabang', 'grade'])
            ->keyBy('id');

        $filtered = [];
        foreach ($approverIds as $approverId) {
            $approverId = (int) $approverId;
            $approver = $approvers->get($approverId);
            if (!$approver) {
                continue;
            }
            if ($this->shouldDropBranchOperationalSupervisor($submitter, $approver)) {
                continue;
            }
            $filtered[] = $approverId;
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Atasan langsung untuk naik hirarki teknis/HO — SPV operasional cabang tidak dijadikan akar BFS.
     *
     * @return list<int>
     */
    public function directAtasanIdsForTechnicalChain(MasterKaryawan $submitter): array
    {
        $ids = $this->decodeDirectAtasanIds($submitter);
        if ($ids === []) {
            return [];
        }

        $people = MasterKaryawan::query()
            ->whereIn('id', $ids)
            ->where('is_active', 1)
            ->get(['id', 'id_cabang', 'grade'])
            ->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $person = $people->get((int) $id);
            if (!$person || $this->shouldDropBranchOperationalSupervisor($submitter, $person)) {
                continue;
            }
            $out[] = (int) $person->id;
        }

        return array_values(array_unique($out));
    }

    /** @return list<int> */
    private function decodeDirectAtasanIds(MasterKaryawan $employee): array
    {
        $raw = $employee->atasan_langsung ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded), fn ($id) => $id > 0));
    }

    private function shouldDropBranchOperationalSupervisor(MasterKaryawan $submitter, MasterKaryawan $approver): bool
    {
        $hoCabangId = $this->hoCabangId();
        $approverCabang = (int) ($approver->id_cabang ?? 0);
        $submitterCabang = (int) ($submitter->id_cabang ?? 0);

        if ($approverCabang === $hoCabangId) {
            return false;
        }

        if ($this->normalizeGrade($approver->grade ?? '') !== 'SUPERVISOR') {
            return false;
        }

        return $submitterCabang > 0 && $approverCabang === $submitterCabang;
    }

    private function isSampler(MasterKaryawan $submitter): bool
    {
        $configuredIds = config('greatday.sampler_jabatan_ids', [94]);
        if (is_array($configuredIds) && in_array((int) ($submitter->id_jabatan ?? 0), $configuredIds, true)) {
            return true;
        }

        $label = KaryawanPresentation::jabatanLabel($submitter) ?: ($submitter->jabatan ?? '');
        $label = mb_strtolower(trim((string) $label));

        return $label !== '' && str_contains($label, 'sampler');
    }

    private function hoCabangId(): int
    {
        return (int) config('greatday.sampler_branch_ho_cabang_id', 1);
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
