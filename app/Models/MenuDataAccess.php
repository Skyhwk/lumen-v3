<?php

namespace App\Models;

class MenuDataAccess extends Sector
{
    protected $table = 'menu_data_access';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'grades' => 'array',
    ];

    /**
     * Hierarki grade (rendah → tinggi). Alias di level yang sama.
     * Rank lebih besar = lebih tinggi.
     */
    public const GRADE_RANKS = [
        'STAFF' => 1,
        'SUPERVISOR' => 2,
        'MANAGER' => 3,
        'SENIOR MANAGER' => 4,
        'DIREKSI' => 5,
        'DIRECTOR' => 5,
        'DIREKTUR' => 5,
    ];

    /** Opsi kanonik untuk UI (tanpa alias). */
    public const GRADE_OPTIONS = [
        'STAFF',
        'SUPERVISOR',
        'MANAGER',
        'SENIOR MANAGER',
        'DIREKSI',
    ];

    public static function normalizeGrade(?string $grade): string
    {
        return strtoupper(trim((string) $grade));
    }

    /**
     * @param  mixed  $grades
     * @return list<string>
     */
    public static function normalizeGrades($grades): array
    {
        if (is_string($grades)) {
            $decoded = json_decode($grades, true);
            $grades = json_last_error() === JSON_ERROR_NONE ? $decoded : [$grades];
        }

        if (!is_array($grades)) {
            return [];
        }

        return collect($grades)
            ->map(fn($grade) => self::normalizeGrade(is_scalar($grade) ? (string) $grade : ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeMenuKey(?string $menuKey): string
    {
        return str_replace('_', '-', strtolower(trim((string) $menuKey)));
    }

    public static function isValidMenuKey(?string $menuKey): bool
    {
        $key = self::normalizeMenuKey($menuKey);

        return $key !== '' && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key);
    }

    public static function gradeRank(string $grade): ?int
    {
        $normalized = self::normalizeGrade($grade);

        return self::GRADE_RANKS[$normalized] ?? null;
    }

    public static function isKnownGrade(string $grade): bool
    {
        return self::gradeRank($grade) !== null;
    }

    /**
     * Expand grade minimum → grade tersebut + semua grade di atasnya (termasuk alias).
     *
     * @return list<string>
     */
    public static function expandFromMinGrade(string $minGrade): array
    {
        $minRank = self::gradeRank($minGrade);
        if ($minRank === null) {
            return [];
        }

        $expanded = [];
        foreach (self::GRADE_RANKS as $grade => $rank) {
            if ($rank >= $minRank) {
                $expanded[] = $grade;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Ambil grade minimum (paling rendah) dari daftar grades tersimpan.
     */
    public static function resolveMinGrade(array $grades): ?string
    {
        $minRank = null;
        $minCanonical = null;

        foreach (self::normalizeGrades($grades) as $grade) {
            $rank = self::gradeRank($grade);
            if ($rank === null) {
                continue;
            }
            if ($minRank === null || $rank < $minRank) {
                $minRank = $rank;
                $minCanonical = self::canonicalGrade($grade);
            }
        }

        return $minCanonical;
    }

    /** Samakan alias ke label kanonik UI. */
    public static function canonicalGrade(string $grade): string
    {
        $normalized = self::normalizeGrade($grade);
        $rank = self::gradeRank($normalized);
        if ($rank === null) {
            return $normalized;
        }

        foreach (self::GRADE_OPTIONS as $option) {
            if (self::gradeRank($option) === $rank) {
                return $option;
            }
        }

        return $normalized;
    }

    public static function accessLabelFromMinGrade(?string $minGrade): string
    {
        $min = self::normalizeGrade($minGrade);
        if ($min === '' || !self::isKnownGrade($min)) {
            return '-';
        }

        $canonical = self::canonicalGrade($min);
        $maxOption = self::GRADE_OPTIONS[count(self::GRADE_OPTIONS) - 1];

        if ($canonical === $maxOption) {
            return $canonical . ' saja';
        }

        return $canonical . ' ke atas';
    }

    /**
     * @return list<array{id: string, text: string, rank: int, hint: string}>
     */
    public static function gradeOptionList(): array
    {
        $items = [];
        foreach (self::GRADE_OPTIONS as $grade) {
            $items[] = [
                'id' => $grade,
                'text' => $grade,
                'rank' => self::gradeRank($grade),
                'hint' => self::accessLabelFromMinGrade($grade),
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public static function activeGradesForMenu(string $menuKey): array
    {
        $key = self::normalizeMenuKey($menuKey);
        if ($key === '') {
            return [];
        }

        $legacyKey = str_replace('-', '_', $key);

        $row = static::query()
            ->where('is_active', true)
            ->where(function ($q) use ($key, $legacyKey) {
                $q->where('menu_key', $key);
                if ($legacyKey !== $key) {
                    $q->orWhere('menu_key', $legacyKey);
                }
            })
            ->first(['grades']);

        if (!$row) {
            return [];
        }

        $grades = self::normalizeGrades($row->grades);
        $minGrade = self::resolveMinGrade($grades);

        // Pastikan selalu konsisten dengan hierarki (re-expand dari min)
        if ($minGrade !== null) {
            return self::expandFromMinGrade($minGrade);
        }

        return $grades;
    }
}
