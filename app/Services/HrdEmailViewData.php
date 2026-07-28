<?php

namespace App\Services;

class HrdEmailViewData
{
    public static function contactLine($data): string
    {
        $phone = $data->no_hp ?? null;
        $email = $data->email ?? null;

        if (!$phone) {
            return $email ?: '-';
        }

        if (!$email) {
            return $phone;
        }

        return $phone . ' / ' . $email;
    }

    public static function photoUrl($data): string
    {
        if (empty($data->foto_selfie)) {
            return '';
        }

        return 'https://apps.intilab.com/v3/public/recruitment/foto/' . $data->foto_selfie;
    }

    public static function decodeJsonField($value): array
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_filter($decoded);
    }

    public static function formatRupiah($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    public static function prepareCvData($data): array
    {
        return [
            'data' => $data,
            'photoUrl' => self::photoUrl($data),
            'pendidikan' => self::decodeJsonField($data->pendidikan ?? null),
            'pengalamanKerja' => self::decodeJsonField($data->pengalaman_kerja ?? null),
            'skills' => self::decodeJsonField($data->skill ?? null),
            'skillBahasa' => self::decodeJsonField($data->skill_bahasa ?? null),
            'minat' => self::decodeJsonField($data->minat ?? null),
            'organisasi' => self::decodeJsonField($data->organisasi ?? null),
            'referensi' => self::decodeJsonField($data->referensi ?? null),
            'sertifikat' => self::decodeJsonField($data->sertifikat ?? null),
            'kursus' => self::decodeJsonField($data->kursus ?? null),
            'salaryFormatted' => self::formatRupiah($data->salary_user ?? null),
        ];
    }
}
