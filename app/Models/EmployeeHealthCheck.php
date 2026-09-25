<?php

namespace App\Models;

use Carbon\Carbon;

class EmployeeHealthCheck extends Sector
{
    protected $table = 'employee_health_checks';
    public $timestamps = false;
    protected $guarded = [];

    public const TENSI_RENDAH = 'Rendah';
    public const TENSI_RATA_RATA = 'Rata-Rata';
    public const TENSI_TINGGI = 'Tinggi';

    public const STOK_NORMAL = 'normal';
    public const STOK_ADA_KELAINAN = 'ada_kelainan';

    public const KET_SEHAT = 'sehat';
    public const KET_PERLU_OBSERVASI = 'perlu_observasi';
    public const KET_PERLU_RUJUKAN = 'perlu_rujukan';
    public const KET_IZIN = 'izin';
    public const KET_SAKIT = 'sakit';
    public const KET_LAINNYA = 'lainnya';

    public static function classifyTensi(int $tensiSistolik): string
    {
        if ($tensiSistolik <= 90) {
            return self::TENSI_RENDAH;
        }

        if ($tensiSistolik <= 135) {
            return self::TENSI_RATA_RATA;
        }

        return self::TENSI_TINGGI;
    }

    public static function normalizeKeluhanInput($keluhan): ?string
    {
        $value = trim((string) ($keluhan ?? ''));
        if ($value === '') {
            return null;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = trim(preg_replace(
            '/\s+/u',
            ' ',
            strip_tags(str_replace(['&nbsp;', '&#160;'], ' ', $decoded))
        ));

        return $plainText === '' ? null : $value;
    }

    public static function formatKeluhanDisplay($keluhan): string
    {
        $normalized = self::normalizeKeluhanInput($keluhan);

        return $normalized === null ? '-' : $normalized;
    }

    public static function formatTensiLabel($sistolik, $diastolik): string
    {
        if ($sistolik === null || $diastolik === null) {
            return '-';
        }

        return $sistolik . '/' . $diastolik;
    }

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'employee_id', 'id');
    }

    public static function generateSkkNumber(string $checkDate): string
    {
        $date = Carbon::parse($checkDate);
        $yearPart = $date->format('y');
        $monthPart = self::monthToRoman((int) $date->format('n'));

        do {
            $hex = strtoupper(bin2hex(random_bytes(4)));
            $skkNumber = 'SKK/' . $yearPart . '/' . $monthPart . '/' . $hex;
        } while (static::query()->where('skk_number', $skkNumber)->exists());

        return $skkNumber;
    }

    private static function monthToRoman(int $month): string
    {
        $map = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $map[$month] ?? 'I';
    }
}
