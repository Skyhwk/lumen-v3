<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * BAK hanya terlihat oleh penandatangan (pengaju) dan atasan yang relevan (hirarki / rantai verifikasi).
 */
final class BakDocumentVisibilityService
{
    public function canViewRow(object $row, int $viewerKaryawanId): bool
    {
        if ($viewerKaryawanId <= 0) {
            return false;
        }

        $penandatanganId = (int) ($row->penandatangan_karyawan_id ?? 0);
        if ($penandatanganId === $viewerKaryawanId) {
            return true;
        }

        if ((int) ($row->rejected_atasan_karyawan_id ?? 0) === $viewerKaryawanId) {
            return true;
        }

        if ($this->viewerListedInApprovalChain($row, $viewerKaryawanId)) {
            return true;
        }

        $viewer = MasterKaryawan::query()
            ->where('id', $viewerKaryawanId)
            ->where('is_active', true)
            ->first(['id', 'grade']);

        if (!$viewer || $penandatanganId <= 0) {
            return false;
        }

        return AtasanApprovalScope::isSubordinateKaryawan($viewer, $penandatanganId);
    }

    public function canViewDocument(BeritaAcaraKejadianDisipliner $document, int $viewerKaryawanId): bool
    {
        return $this->canViewRow($document, $viewerKaryawanId);
    }

    /**
     * @param Builder<BeritaAcaraKejadianDisipliner> $query
     */
    public function applyListScope(Builder $query, int $viewerKaryawanId): void
    {
        if ($viewerKaryawanId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $viewer = MasterKaryawan::query()
            ->where('id', $viewerKaryawanId)
            ->where('is_active', true)
            ->first(['id', 'grade']);

        $penandatanganIds = [$viewerKaryawanId];
        if ($viewer) {
            $penandatanganIds = array_values(array_unique(array_merge(
                $penandatanganIds,
                AtasanApprovalScope::subordinateKaryawanIds($viewer)
            )));
        }

        $chainNeedle = '%"karyawan_id":' . $viewerKaryawanId . '%';

        $query->where(function (Builder $inner) use ($penandatanganIds, $viewerKaryawanId, $chainNeedle) {
            $inner->whereIn('bak.penandatangan_karyawan_id', $penandatanganIds)
                ->orWhere('bak.rejected_atasan_karyawan_id', $viewerKaryawanId)
                ->orWhere('bak.approval_chain', 'like', $chainNeedle);
        });
    }

    public function assertCanViewRow(object $row, int $viewerKaryawanId): void
    {
        if (!$this->canViewRow($row, $viewerKaryawanId)) {
            throw new RuntimeException('Anda tidak memiliki akses untuk melihat BAK ini.');
        }
    }

    /**
     * Tampilkan badge jumlah di tab workflow (pengaju, atasan bermitra, atau pernah di rantai verifikasi).
     */
    public function viewerShowsTabCounts(int $viewerKaryawanId): bool
    {
        if ($viewerKaryawanId <= 0) {
            return false;
        }

        $viewer = MasterKaryawan::query()
            ->where('id', $viewerKaryawanId)
            ->where('is_active', true)
            ->first(['id', 'grade']);

        if (!$viewer) {
            return false;
        }

        if (AtasanApprovalScope::subordinateKaryawanIds($viewer) !== []) {
            return true;
        }

        $chainNeedle = '%"karyawan_id":' . $viewerKaryawanId . '%';

        return BeritaAcaraKejadianDisipliner::query()
            ->where('is_active', true)
            ->where(function (Builder $inner) use ($viewerKaryawanId, $chainNeedle) {
                $inner->where('penandatangan_karyawan_id', $viewerKaryawanId)
                    ->orWhere('rejected_atasan_karyawan_id', $viewerKaryawanId)
                    ->orWhere('approval_chain', 'like', $chainNeedle);
            })
            ->exists();
    }

    private function viewerListedInApprovalChain(object $row, int $viewerKaryawanId): bool
    {
        $raw = $row->approval_chain ?? null;
        if ($raw === null || $raw === '') {
            return false;
        }

        $chain = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($chain)) {
            return false;
        }

        foreach ($chain as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int) ($item['karyawan_id'] ?? 0) === $viewerKaryawanId) {
                return true;
            }
        }

        return false;
    }
}