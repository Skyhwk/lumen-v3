<?php

namespace App\Models;

class MonitoringPeralatan extends Sector
{
    protected $table = 'monitoring_peralatan';

    protected $guarded = ['id'];

    protected $casts = [
        'id_cabang' => 'integer',
        'jumlah_alat_tersedia' => 'integer',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }
}
