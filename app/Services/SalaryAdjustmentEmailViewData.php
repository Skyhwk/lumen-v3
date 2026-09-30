<?php

namespace App\Services;

use Carbon\Carbon;

class SalaryAdjustmentEmailViewData
{
    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function formatRupiah($value): string
    {
        return 'Rp ' . number_format((float) ($value ?? 0), 0, ',', '.');
    }

    public static function formatBulanEfektif(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^(\d{4})-(\d{2})$/', $value, $match)) {
            return $value !== '' ? $value : '-';
        }

        $month = (int) $match[2];
        $year = $match[1];

        return (self::MONTH_NAMES[$month] ?? $match[2]) . ' ' . $year;
    }

    public static function employeeSummaryRows(array $request): array
    {
        return [
            'No. Dokumen' => $request['no_document'] ?? '-',
            'Nama Karyawan' => $request['nama_lengkap'] ?? '-',
            'Manager Pengaju' => $request['manager_nama'] ?? '-',
            'Jabatan' => $request['jabatan'] ?? '-',
            'Bulan Efektif' => self::formatBulanEfektif($request['bulan_efektif'] ?? null),
        ];
    }

    public static function nominalBlock(array $request, string $type = 'active'): array
    {
        if ($type === 'submitted') {
            return [
                'Delta Gaji Pokok' => self::formatRupiah($request['submitted_adjustment_gaji_pokok'] ?? $request['adjustment_gaji_pokok'] ?? 0),
                'Delta Tunjangan' => self::formatRupiah($request['submitted_adjustment_tunjangan'] ?? $request['adjustment_tunjangan'] ?? 0),
                'Target Gaji Pokok' => self::formatRupiah($request['submitted_requested_gaji_pokok'] ?? $request['requested_gaji_pokok'] ?? 0),
                'Target Tunjangan' => self::formatRupiah($request['submitted_requested_tunjangan_kerja'] ?? $request['requested_tunjangan_kerja'] ?? 0),
            ];
        }

        if ($type === 'hrd') {
            return [
                'Delta Gaji Pokok' => self::formatRupiah($request['hrd_final_adjustment_gaji_pokok'] ?? $request['adjustment_gaji_pokok'] ?? 0),
                'Delta Tunjangan' => self::formatRupiah($request['hrd_final_adjustment_tunjangan'] ?? $request['adjustment_tunjangan'] ?? 0),
                'Target Gaji Pokok' => self::formatRupiah($request['hrd_final_requested_gaji_pokok'] ?? $request['requested_gaji_pokok'] ?? 0),
                'Target Tunjangan' => self::formatRupiah($request['hrd_final_requested_tunjangan_kerja'] ?? $request['requested_tunjangan_kerja'] ?? 0),
            ];
        }

        return [
            'Gaji Saat Ini' => self::formatRupiah($request['current_gaji_pokok'] ?? 0),
            'Tunjangan Saat Ini' => self::formatRupiah($request['current_tunjangan_kerja'] ?? 0),
            'Delta Gaji Pokok' => self::formatRupiah($request['adjustment_gaji_pokok'] ?? 0),
            'Delta Tunjangan' => self::formatRupiah($request['adjustment_tunjangan'] ?? 0),
            'Target Gaji Pokok' => self::formatRupiah($request['requested_gaji_pokok'] ?? 0),
            'Target Tunjangan' => self::formatRupiah($request['requested_tunjangan_kerja'] ?? 0),
        ];
    }

    public static function showHrdColumn(array $request): bool
    {
        return !empty($request['has_hrd_final_decision'])
            || !empty($request['final_eval_approved_at'])
            || !empty($request['changed_by_hrd'])
            || !empty($request['hrd_final_adjustment_gaji_pokok'])
            || !empty($request['hrd_final_adjustment_tunjangan'])
            || !empty($request['submitted_adjustment_gaji_pokok']);
    }

    public static function showFinanceDiff(array $request): bool
    {
        return !empty($request['changed_by_finance']);
    }

    public static function plainText(?string $html): string
    {
        $text = trim(strip_tags((string) $html));

        return $text !== '' ? $text : '-';
    }

    public static function formatLogTime(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->timezone('Asia/Jakarta')->format('d M Y H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    public static function employeePhotoDataUri(?string $filename): string
    {
        $path = self::resolveEmployeePhotoPath($filename);
        if ($path === null) {
            return '';
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    public static function employeePhotoUrl(?string $filename): string
    {
        $basename = basename(trim((string) $filename));
        if ($basename === '') {
            $basename = 'no_image.jpg';
        }

        $baseUrl = rtrim((string) env('APP_URL', ''), '/');

        return $baseUrl . '/public/Foto_Karyawan/' . $basename;
    }

    public static function papiScoreStyle($score): array
    {
        $value = (float) ($score ?? 0);

        if ($value >= 7) {
            return ['bg' => '#ecfdf5', 'color' => '#047857', 'border' => '#a7f3d0'];
        }

        if ($value >= 4) {
            return ['bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'];
        }

        return ['bg' => '#f4f4f5', 'color' => '#52525b', 'border' => '#d4d4d8'];
    }

    private static function resolveEmployeePhotoPath(?string $filename): ?string
    {
        $basename = basename(trim((string) $filename));
        $candidates = array_values(array_unique(array_filter([
            $basename !== '' ? $basename : null,
            'no_image.jpg',
            'no_image_2.jpg',
        ])));

        foreach ($candidates as $candidate) {
            $path = public_path('Foto_Karyawan/' . $candidate);
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
