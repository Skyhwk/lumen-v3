<?php

namespace App\Services;

use App\Models\LimsDocument;
use Carbon\Carbon;

class LimsDocumentWorkflowService
{
    public function isManager($karyawan): bool
    {
        if (!$karyawan) {
            return false;
        }

        return strtoupper((string) ($karyawan->grade ?? '')) === 'MANAGER';
    }

    public function getPengesahanDefaults(): array
    {
        return [
            'nama_pengesahan' => config('pengesahanlims.nama_pengesahan', 'Sucita Rahmi'),
            'jabatan_pengesah' => config('pengesahanlims.jabatan_pengesah', 'Manager Puncak'),
            'tanggal_pengesahan' => Carbon::today()->format('Y-m-d'),
        ];
    }

    public function getApprovalDefaults($karyawan, ?string $fallbackName = null): array
    {
        return [
            'nama_pengesahan' => $karyawan->nama_lengkap ?? $fallbackName ?? '',
            'jabatan_pengesah' => $karyawan->jabatan ?? '',
            'tanggal_pengesahan' => Carbon::today()->format('Y-m-d'),
        ];
    }

    public function getComposerDefaults($karyawan, ?string $fallbackName = null): array
    {
        return [
            'disusun_oleh' => $karyawan->nama_lengkap ?? $fallbackName ?? '',
            'jabatan_penyusun' => $karyawan->jabatan ?? '',
            'tanggal_cetak' => Carbon::today()->format('Y-m-d'),
        ];
    }

    public function getInitializeData($karyawan, ?string $fallbackName = null): array
    {
        return array_merge(
            $this->getComposerDefaults($karyawan, $fallbackName),
            [
                'can_approve' => $this->isManager($karyawan),
                'pengesahan' => $this->getPengesahanDefaults(),
                'persetujuan' => $this->getApprovalDefaults($karyawan, $fallbackName),
            ]
        );
    }

    /**
     * Tombol setujui hilang hanya jika user sudah pernah menyetujui
     * dengan nama & jabatan dirinya sendiri (bukan atas nama orang lain).
     */
    public function hasUserSelfApproved(LimsDocument $document, $karyawan, ?string $fallbackName = null): bool
    {
        $userName = trim($karyawan->nama_lengkap ?? $fallbackName ?? '');
        $userJabatan = trim($karyawan->jabatan ?? '');
        $approvedBy = trim($karyawan->nama_lengkap ?? $fallbackName ?? '');

        if ($userName === '' || $approvedBy === '') {
            return false;
        }

        $document->loadMissing('approvals');

        return $document->approvals
            ->where('action', 'approve')
            ->contains(function ($item) use ($userName, $userJabatan, $approvedBy) {
                return strcasecmp(trim($item->approved_by ?? ''), $approvedBy) === 0
                    && strcasecmp(trim($item->nama ?? ''), $userName) === 0
                    && strcasecmp(trim($item->jabatan ?? ''), $userJabatan) === 0;
            });
    }

    public function isSelfApproval($nama, $jabatan, $karyawan, ?string $fallbackName = null): bool
    {
        $userName = trim($karyawan->nama_lengkap ?? $fallbackName ?? '');
        $userJabatan = trim($karyawan->jabatan ?? '');

        return strcasecmp(trim($nama ?? ''), $userName) === 0
            && strcasecmp(trim($jabatan ?? ''), $userJabatan) === 0;
    }

    public function canUserApprove(LimsDocument $document, $karyawan, ?string $userName): bool
    {
        if ($document->status === 'legalized') {
            return false;
        }

        if (!$this->isManager($karyawan)) {
            return false;
        }

        return !$this->hasUserSelfApproved($document, $karyawan, $userName);
    }

    public function canUserLegalize(LimsDocument $document): bool
    {
        return $document->status !== 'legalized';
    }

    public function canUserDelete(LimsDocument $document): bool
    {
        if ($document->status === 'legalized') {
            return false;
        }

        $document->loadMissing('approvals');

        return $document->approvals->where('action', 'approve')->isEmpty();
    }
}
