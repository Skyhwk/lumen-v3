<?php

namespace App\Models;

class MonitoringPeralatan extends Sector
{
    public const STATUS_ALAT = [
        'Tersedia',
        'Rusak',
        'Sedang Di-maintenance',
        'Sedang Dikalibrasi',
        'Sedang Digunakan',
    ];

    public const STATUS_ALAT_PERIODE = [
        'Sedang Di-maintenance',
        'Sedang Dikalibrasi',
        'Sedang Digunakan',
    ];

    protected $table = 'monitoring_peralatan';

    protected $guarded = ['id'];

    protected $casts = [
        'id_peralatan' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function peralatan()
    {
        return $this->belongsTo(Peralatan::class, 'id_peralatan');
    }

    public static function requiresPeriod(?string $status): bool
    {
        return in_array((string) $status, self::STATUS_ALAT_PERIODE, true);
    }
}
