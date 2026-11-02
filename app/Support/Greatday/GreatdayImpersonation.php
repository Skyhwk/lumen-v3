<?php

namespace App\Support\Greatday;

class GreatdayImpersonation
{
    /** ID master_karyawan yang boleh impersonate (kebijakan administrator). */
    public const ALLOWED_KARYAWAN_IDS = [1, 127, 152];

    public static function canImpersonate(int $karyawanId): bool
    {
        return in_array($karyawanId, self::ALLOWED_KARYAWAN_IDS, true);
    }
}
