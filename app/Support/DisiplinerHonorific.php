<?php

namespace App\Support;

/**
 * Sapaan karyawan pada dokumen modul Disipliner (SP, ST, dll.).
 * Perempuan / cewek → Saudari; selain itu (laki-laki / cowok / kosong) → Saudara.
 */
final class DisiplinerHonorific
{
    public static function fromJenisKelamin(?string $jenisKelamin): string
    {
        $normalized = strtolower(trim((string) $jenisKelamin));
        if ($normalized === '') {
            return 'Saudara';
        }

        if (self::isFemale($normalized)) {
            return 'Saudari';
        }

        return 'Saudara';
    }

    private static function isFemale(string $normalized): bool
    {
        $exactFemale = ['p', 'perempuan', 'cewek', 'female', 'f', 'wanita'];
        if (in_array($normalized, $exactFemale, true)) {
            return true;
        }

        if (str_contains($normalized, 'perempuan') || str_contains($normalized, 'cewek')) {
            return true;
        }

        return false;
    }
}
