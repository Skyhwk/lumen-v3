<?php

namespace App\Services;

use App\Models\MasterKaryawan;

class RecruitmentManagerAccess
{
    public const JABATAN_ID = 3;
    public const JABATAN_NAME = 'Recruitment Manager';

    public static function matches($karyawan): bool
    {
        if (!$karyawan) {
            return false;
        }

        if ((int) ($karyawan->id_jabatan ?? 0) === self::JABATAN_ID) {
            return true;
        }

        return strcasecmp(trim((string) ($karyawan->jabatan ?? '')), self::JABATAN_NAME) === 0;
    }

    public static function forKaryawanId(?int $karyawanId): bool
    {
        if (!$karyawanId) {
            return false;
        }

        $karyawan = MasterKaryawan::query()
            ->where('id', $karyawanId)
            ->first(['id_jabatan', 'jabatan']);

        return self::matches($karyawan);
    }

    public static function namesInDivisi(int $divisiId): array
    {
        if ($divisiId < 1) {
            return [];
        }

        return MasterKaryawan::query()
            ->where('is_active', 1)
            ->where('id_department', $divisiId)
            ->whereNotNull('nama_lengkap')
            ->where('nama_lengkap', '!=', '')
            ->pluck('nama_lengkap')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();
    }
}
