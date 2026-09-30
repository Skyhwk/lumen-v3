<?php

namespace App\Helpers;

class RecruitmentDisplay
{
    private const EXPERIENCE_MAP = [
        'fresh graduate' => 'Lulusan Baru',
        '1 - 3 years' => '1 - 3 Tahun',
        '3 - 5 years' => '3 - 5 Tahun',
        '> 5 years' => 'Lebih dari 5 Tahun',
        'based on qualifications' => 'Sesuai kualifikasi',
    ];

    private const GENDER_MAP = [
        'no_preference' => 'Semua jenis kelamin',
        'no preference' => 'Semua jenis kelamin',
        'all genders' => 'Semua jenis kelamin',
        'laki-laki' => 'Laki-laki',
        'laki laki' => 'Laki-laki',
        'male' => 'Laki-laki',
        'perempuan' => 'Perempuan',
        'female' => 'Perempuan',
    ];

    private const PRIORITY_MAP = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'normal' => 'Normal',
        'high' => 'Tinggi',
        'urgent' => 'Mendesak',
    ];

    private const EDUCATION_MAP = [
        'high school / equivalent' => 'SMA / Setara',
        'associate degree (d1/d2/d3)' => 'Diploma (D1/D2/D3)',
        "bachelor's / applied degree (s1/d4)" => 'S1 / D4',
        "bachelor's degree" => 'S1',
        "master's degree (s2)" => 'S2',
        'doctorate (s3)' => 'S3',
        'sma' => 'SMA',
        'smk' => 'SMK',
        'd1' => 'D1',
        'd2' => 'D2',
        'd3' => 'D3',
        's1' => 'S1',
        's2' => 'S2',
        's3' => 'S3',
    ];

    public static function translateExperience(?string $value, string $fallback = 'Sesuai kualifikasi'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        $normalized = strtolower(trim($value));
        if (isset(self::EXPERIENCE_MAP[$normalized])) {
            return self::EXPERIENCE_MAP[$normalized];
        }

        $translated = preg_replace('/\byears?\b/i', 'Tahun', trim($value));
        $translated = preg_replace('/\bmonths?\b/i', 'Bulan', $translated);

        return $translated ?: $fallback;
    }

    public static function translateGender(?string $value, string $fallback = 'Semua jenis kelamin'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        $normalized = strtolower(str_replace('_', ' ', trim($value)));

        return self::GENDER_MAP[$normalized]
            ?? self::GENDER_MAP[str_replace(' ', '-', $normalized)]
            ?? $fallback;
    }

    public static function translatePriority(?string $value, string $fallback = 'Normal'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        $normalized = strtolower(trim($value));

        return self::PRIORITY_MAP[$normalized] ?? ucfirst($normalized);
    }

    public static function translateEducation(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        return self::EDUCATION_MAP[$normalized] ?? trim($value);
    }

    public static function richTextHasContent(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $text = trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $text !== '';
    }

    public static function richText(?string $value): ?string
    {
        return self::richTextHasContent($value) ? trim($value) : null;
    }

    public static function formatUserAssessmentSummary($job): ?string
    {
        if ((int) ($job->use_user_assessment ?? 0) !== 1) {
            return null;
        }

        $count = (int) ($job->user_assessment_question_count ?? 0);
        if ($count < 1) {
            return null;
        }

        $summary = $count . ' soal';
        $hasTimeLimit = (int) ($job->user_assessment_has_time_limit ?? 0) === 1;
        $duration = (int) ($job->user_assessment_duration_minutes ?? 0);

        if ($hasTimeLimit && $duration > 0) {
            $summary .= ', durasi ' . $duration . ' menit';
        } else {
            $summary .= ', tanpa batas waktu';
        }

        return $summary;
    }
}
