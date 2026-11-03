<?php

namespace App\Models;

use App\Models\Sector;

class JadwalMobilisasi extends Sector
{
    protected $table = 'jadwal_mobilisasi';

    protected $guarded = [];

    protected $casts = [
        'batch_jadwal_ids' => 'array',
        'peralatan_sampling' => 'array',
        'is_active' => 'boolean',
    ];

    public $timestamps = false;
}
