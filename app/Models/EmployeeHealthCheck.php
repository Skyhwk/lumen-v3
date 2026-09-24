<?php

namespace App\Models;

class EmployeeHealthCheck extends Sector
{
    protected $table = 'employee_health_checks';
    public $timestamps = false;
    protected $guarded = [];

    public const TENSI_RENDAH = 'Rendah';
    public const TENSI_RATA_RATA = 'Rata-Rata';
    public const TENSI_NORMAL = 'Normal';
    public const TENSI_NORMAL_TINGGI = 'Normal Tinggi';
    public const TENSI_TINGGI = 'Tinggi';

    public const STOK_NORMAL = 'normal';
    public const STOK_ADA_KELAINAN = 'ada_kelainan';

    public const KET_SEHAT = 'sehat';
    public const KET_PERLU_OBSERVASI = 'perlu_observasi';
    public const KET_PERLU_RUJUKAN = 'perlu_rujukan';
    public const KET_IZIN = 'izin';
    public const KET_SAKIT = 'sakit';
    public const KET_LAINNYA = 'lainnya';

    public static function classifyTensi(int $tensi): string
    {
        if ($tensi < 90) {
            return self::TENSI_RENDAH;
        }

        if ($tensi <= 99) {
            return self::TENSI_RATA_RATA;
        }

        if ($tensi <= 119) {
            return self::TENSI_NORMAL;
        }

        if ($tensi <= 135) {
            return self::TENSI_NORMAL_TINGGI;
        }

        return self::TENSI_TINGGI;
    }

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'employee_id', 'id');
    }
}
