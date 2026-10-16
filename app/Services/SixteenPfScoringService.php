<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SixteenPfScoringService
{
    private const PRIMARY_FACTORS = ['A', 'B', 'C', 'E', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'Q1', 'Q2', 'Q3', 'Q4'];

    /** @var array|null norm_group => factor => raw => sten */
    private static $normTablesCache;

    /** @var array|null factor => ['low' => string, 'high' => string] */
    private static $psychogramProfileCache;

    /** @var array|null factor => ['low' => string, 'high' => string] short labels */
    private static $psychogramLabelsCache;

    /** @var array|null */
    private $bundle;

    public function score(array $questions, array $answers, ?object $employee = null): array
    {
        $raw = array_fill_keys(array_merge(['MD'], self::PRIMARY_FACTORS), 0);
        $answered = 0;

        foreach ($questions as $question) {
            $questionId = (string) ($question['id'] ?? '');
            if ($questionId === '' || !array_key_exists($questionId, $answers)) {
                continue;
            }

            $answer = $answers[$questionId];
            if ($answer === null || $answer === '') {
                continue;
            }

            $choice = strtolower(is_array($answer) ? (string) reset($answer) : (string) $answer);
            if (!in_array($choice, ['a', 'b', 'c'], true)) {
                continue;
            }

            $key = $question['scoring_key'] ?? null;
            if (!is_array($key)) {
                continue;
            }

            $factor = strtoupper(trim((string) ($key['factor'] ?? '')));
            $points = $key['points'] ?? [];
            if ($factor === '' || !isset($points[$choice])) {
                continue;
            }

            $raw[$factor] = ($raw[$factor] ?? 0) + (int) $points[$choice];
            $answered++;
        }

        $normGroup = $this->resolveNormGroup($employee);
        $gender = $this->resolveGender($employee);
        $normTables = $this->normTablesForGroup($normGroup);

        $factors = [];
        foreach (array_merge(['MD'], self::PRIMARY_FACTORS) as $code) {
            $factorRaw = (int) ($raw[$code] ?? 0);
            $factors[$code] = [
                'raw' => $factorRaw,
                'sten' => $this->lookupSten($normTables, $code, $factorRaw),
            ];
        }

        $labels = $this->psychogramLabels();
        $psychogram = [];
        foreach (self::PRIMARY_FACTORS as $code) {
            $sten = (int) ($factors[$code]['sten'] ?? 5);
            $psychogram[] = [
                'factor' => $code,
                'sten' => $sten,
                'band' => $this->stenBand($sten),
                'low_label' => $labels[$code]['low'] ?? '',
                'high_label' => $labels[$code]['high'] ?? '',
            ];
        }

        return [
            'engine' => '16pf',
            'answered' => $answered,
            'total_questions' => count($questions),
            'norm_group' => $normGroup,
            'gender' => $gender,
            'raw_scores' => $raw,
            'factors' => $factors,
            'psychogram' => $psychogram,
            'score' => null,
        ];
    }

    public function resolveNormGroup(?object $employee): string
    {
        $education = strtolower(trim((string) ($employee->pendidikan ?? $employee->last_education ?? '')));
        $tier = 'smp_sma';

        if (preg_match('/\b(s1|s2|s3|sarjana|strata|univ|universitas)\b/u', $education) === 1) {
            $tier = 's1';
        } elseif (preg_match('/\b(umum|masyarakat)\b/u', $education) === 1) {
            $tier = 'umum';
        }

        $gender = strtolower($this->resolveGender($employee));

        return $tier . '_' . ($gender === 'p' ? 'p' : 'l');
    }

    public function resolveGender(?object $employee): string
    {
        $value = strtoupper(trim((string) ($employee->jenis_kelamin ?? $employee->gender ?? 'L')));

        return $value === 'P' ? 'P' : 'L';
    }

    private function normTablesForGroup(string $groupKey): array
    {
        $all = $this->loadNormTablesFromDatabase();
        if ($all !== []) {
            if (isset($all[$groupKey]) && is_array($all[$groupKey])) {
                return $all[$groupKey];
            }

            $fallback = preg_replace('/_[lp]$/', '_lp', $groupKey);

            return is_array($all[$fallback] ?? null) ? $all[$fallback] : [];
        }

        $bundle = $this->loadBundle();
        if (isset($bundle['norms'][$groupKey]) && is_array($bundle['norms'][$groupKey])) {
            return $bundle['norms'][$groupKey];
        }

        $fallback = preg_replace('/_[lp]$/', '_lp', $groupKey);
        $norms = $bundle['norms'][$fallback] ?? [];

        return is_array($norms) ? $norms : [];
    }

    /**
     * @return array<string, array<string, array<int, int>>>
     */
    private function loadNormTablesFromDatabase(): array
    {
        if (self::$normTablesCache !== null) {
            return self::$normTablesCache;
        }

        self::$normTablesCache = [];

        try {
            if (!DB::connection('mysql')->getSchemaBuilder()->hasTable('psychometric_16pf_norms')) {
                return self::$normTablesCache;
            }
        } catch (\Throwable $e) {
            return self::$normTablesCache;
        }

        $count = (int) DB::connection('mysql')->table('psychometric_16pf_norms')->count();
        if ($count === 0) {
            return self::$normTablesCache;
        }

        $rows = DB::connection('mysql')->table('psychometric_16pf_norms')
            ->orderBy('norm_group')
            ->orderBy('factor')
            ->orderBy('raw_score')
            ->get(['norm_group', 'factor', 'raw_score', 'sten']);

        foreach ($rows as $row) {
            $group = (string) $row->norm_group;
            $factor = strtoupper((string) $row->factor);
            $raw = (int) $row->raw_score;
            if (!isset(self::$normTablesCache[$group])) {
                self::$normTablesCache[$group] = [];
            }
            if (!isset(self::$normTablesCache[$group][$factor])) {
                self::$normTablesCache[$group][$factor] = [];
            }
            self::$normTablesCache[$group][$factor][$raw] = (int) $row->sten;
        }

        return self::$normTablesCache;
    }

    private function lookupSten(array $normTables, string $factor, int $raw): int
    {
        $table = $normTables[$factor] ?? [];
        if (!is_array($table) || empty($table)) {
            return 5;
        }

        ksort($table);
        $sten = 5;
        foreach ($table as $rawKey => $value) {
            if ($raw >= (int) $rawKey) {
                $sten = (int) $value;
            }
        }

        return max(1, min(10, $sten));
    }

    private function stenBand(int $sten): string
    {
        if ($sten <= 4) {
            return 'rendah';
        }
        if ($sten >= 8) {
            return 'tinggi';
        }

        return 'rata-rata';
    }

    /**
     * Uraian kutub panjang (sheet Grafik Psikogram) — factor => low/high.
     *
     * @return array<string, array{low: string, high: string}>
     */
    public function psychogramProfile(): array
    {
        $fromDb = $this->loadPsychogramProfileFromDatabase();
        if ($fromDb !== []) {
            return $fromDb;
        }

        $bundle = $this->loadBundle();
        $profile = is_array($bundle['psychogram_profile'] ?? null) ? $bundle['psychogram_profile'] : [];

        return is_array($profile) ? $profile : [];
    }

    private function psychogramLabels(): array
    {
        $fromDb = $this->loadPsychogramLabelsFromDatabase();
        if ($fromDb !== []) {
            return $fromDb;
        }

        $bundle = $this->loadBundle();

        return is_array($bundle['psychogram_labels'] ?? null) ? $bundle['psychogram_labels'] : [];
    }

    /**
     * @return array<string, array{low: string, high: string}>
     */
    private function loadPsychogramProfileFromDatabase(): array
    {
        if (self::$psychogramProfileCache !== null) {
            return self::$psychogramProfileCache;
        }

        self::$psychogramProfileCache = [];

        if (!$this->hasPsychogramProfilesTable()) {
            return self::$psychogramProfileCache;
        }

        $count = (int) DB::connection('mysql')->table('psychometric_16pf_profiles')->count();
        if ($count === 0) {
            return self::$psychogramProfileCache;
        }

        $rows = DB::connection('mysql')->table('psychometric_16pf_profiles')
            ->orderBy('sort_order')
            ->orderBy('factor')
            ->get(['factor', 'low_text', 'high_text']);

        foreach ($rows as $row) {
            $factor = strtoupper(trim((string) $row->factor));
            self::$psychogramProfileCache[$factor] = [
                'low' => trim((string) $row->low_text),
                'high' => trim((string) $row->high_text),
            ];
        }

        return self::$psychogramProfileCache;
    }

    /**
     * @return array<string, array{low: string, high: string}>
     */
    private function loadPsychogramLabelsFromDatabase(): array
    {
        if (self::$psychogramLabelsCache !== null) {
            return self::$psychogramLabelsCache;
        }

        self::$psychogramLabelsCache = [];

        if (!$this->hasPsychogramProfilesTable()) {
            return self::$psychogramLabelsCache;
        }

        $count = (int) DB::connection('mysql')->table('psychometric_16pf_profiles')->count();
        if ($count === 0) {
            return self::$psychogramLabelsCache;
        }

        $rows = DB::connection('mysql')->table('psychometric_16pf_profiles')
            ->orderBy('sort_order')
            ->get(['factor', 'short_low', 'short_high']);

        foreach ($rows as $row) {
            $factor = strtoupper(trim((string) $row->factor));
            $low = trim((string) ($row->short_low ?? ''));
            $high = trim((string) ($row->short_high ?? ''));
            if ($low === '' && $high === '') {
                continue;
            }
            self::$psychogramLabelsCache[$factor] = [
                'low' => $low,
                'high' => $high,
            ];
        }

        return self::$psychogramLabelsCache;
    }

    private function hasPsychogramProfilesTable(): bool
    {
        try {
            return DB::connection('mysql')->getSchemaBuilder()->hasTable('psychometric_16pf_profiles');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function loadBundle(): array
    {
        if ($this->bundle !== null) {
            return $this->bundle;
        }

        $path = storage_path('app/psychometric/16pf_bundle.json');
        if (!is_file($path)) {
            $this->bundle = ['norms' => [], 'psychogram_labels' => []];

            return $this->bundle;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->bundle = is_array($decoded) ? $decoded : ['norms' => [], 'psychogram_labels' => []];

        return $this->bundle;
    }

    public function employeeForRequest(?int $employeeId): ?object
    {
        if (!$employeeId) {
            return null;
        }

        return DB::table('master_karyawan')->where('id', $employeeId)->first();
    }
}
