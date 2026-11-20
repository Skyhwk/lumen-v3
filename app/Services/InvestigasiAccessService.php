<?php

namespace App\Services;

final class InvestigasiAccessService
{
    private const MENU_KEY = 'investigasi-awal';

    /** Grade HR / menu yang boleh mengisi G.1 (Legal). */
    private const LEGAL_MANAGER_GRADES = [
        'LEGAL & DISCIPLINARY MANAGER',
        'LEGAL MANAGER',
        'LEGAL & DISCIPLIN MANAGER',
    ];

    public static function menuKey(): string
    {
        return self::MENU_KEY;
    }

    public static function isLegalManager(?string $grade): bool
    {
        $g = self::normalizeGrade($grade);
        if ($g === '') {
            return false;
        }

        foreach (self::LEGAL_MANAGER_GRADES as $role) {
            if ($g === self::normalizeGrade($role)) {
                return true;
            }
        }

        $allowed = MenuDataAccessService::allowedGrades(self::MENU_KEY);
        foreach ($allowed as $entry) {
            $norm = self::normalizeGrade($entry);
            if ($norm !== '' && $norm === $g) {
                return str_contains($norm, 'LEGAL');
            }
        }

        if (str_contains($g, 'LEGAL') && str_contains($g, 'MANAGER')) {
            return true;
        }

        return str_contains($g, 'LEGAL') && str_contains($g, 'DISCIPLIN');
    }

    /**
     * Grade karyawan sering hanya "MANAGER"; peran Legal diekspresikan lewat kolom jabatan.
     */
    public static function viewerCanActAsLegalManager(
        ?string $grade,
        ?string $jabatan = null,
        $idJabatan = null
    ): bool {
        if (self::isLegalManager($grade)) {
            return true;
        }

        return self::isLegalDisciplinaryJabatan($jabatan, $idJabatan);
    }

    public static function isLegalDisciplinaryJabatan(?string $jabatan, $idJabatan = null): bool
    {
        $j = self::normalizeLabel($jabatan);
        if ($j !== '') {
            foreach (self::LEGAL_MANAGER_GRADES as $role) {
                if ($j === self::normalizeLabel($role)) {
                    return true;
                }
            }
            if (str_contains($j, 'LEGAL') && str_contains($j, 'DISCIPLIN')) {
                return true;
            }
        }

        $configuredId = (int) env('INVESTIGASI_LEGAL_JABATAN_ID', 0);
        $id = (int) $idJabatan;

        return $configuredId > 0 && $id > 0 && $id === $configuredId;
    }

    public static function canFillInvestigasi(?string $grade): bool
    {
        return self::gradeAllowedForMenu($grade);
    }

    public static function gradeAllowedForMenu(?string $grade): bool
    {
        $g = self::normalizeGrade($grade);
        if ($g === '') {
            return false;
        }

        if (!MenuDataAccessService::hasConfiguredGrades(self::MENU_KEY)) {
            return true;
        }

        $allowed = MenuDataAccessService::allowedGrades(self::MENU_KEY);
        foreach ($allowed as $entry) {
            if (self::normalizeGrade($entry) === $g) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeGrade(?string $grade): string
    {
        return self::normalizeLabel($grade);
    }

    public static function normalizeLabel(?string $value): string
    {
        $normalized = strtoupper(trim(str_replace('_', ' ', (string) $value)));
        $normalized = str_replace(['&AMP;', ' AND '], ' & ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}