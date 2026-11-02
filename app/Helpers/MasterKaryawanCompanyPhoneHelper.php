<?php

namespace App\Helpers;

use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;

class MasterKaryawanCompanyPhoneHelper
{
    public const AUTO_POSITION_IDS = [24, 148];

    public const AUTO_POSITION_NAMES = [
        'sales officer',
        'customer relation officer',
    ];

    public static function isAutoFillPosition($positionId): bool
    {
        $id = (int) $positionId;
        if (in_array($id, self::AUTO_POSITION_IDS, true)) {
            return true;
        }

        if ($id <= 0) {
            return false;
        }

        $jabatan = MasterJabatan::find($id);
        if (!$jabatan) {
            return false;
        }

        $name = strtolower(trim((string) ($jabatan->nama_jabatan ?? '')));

        return in_array($name, self::AUTO_POSITION_NAMES, true);
    }

    /**
     * @param  array<string, mixed>|null  $contact
     */
    public static function resolve(?array $contact, $positionId): ?string
    {
        $contact = $contact ?? [];

        if (self::isAutoFillPosition($positionId)) {
            $personalPhone = trim((string) ($contact['phone'] ?? ''));

            return $personalPhone !== '' ? $personalPhone : null;
        }

        $companyPhone = trim((string) ($contact['company_phone'] ?? ''));

        return $companyPhone !== '' ? $companyPhone : null;
    }

    /**
     * Nilai no_telpon_perusahaan dari baris karyawan (untuk backfill / sync).
     */
    public static function resolveFromKaryawanRow(MasterKaryawan $karyawan): ?string
    {
        if (!self::isAutoFillPosition($karyawan->id_jabatan)) {
            return null;
        }

        $personalPhone = trim((string) ($karyawan->no_telpon ?? ''));

        return $personalPhone !== '' ? $personalPhone : null;
    }
}
