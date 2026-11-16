<?php

namespace App\Models;

class MonitoringPeralatan extends Sector
{
    protected $table = 'monitoring_peralatan';

    protected $guarded = ['id'];

    protected $casts = [
        'id_peralatan' => 'integer',
        'is_active' => 'boolean',
    ];

    public function peralatan()
    {
        return $this->belongsTo(Peralatan::class, 'id_peralatan');
    }
}
