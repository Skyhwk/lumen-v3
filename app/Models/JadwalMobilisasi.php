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
        'peralatan_aktual' => 'array',
        'is_active' => 'boolean',
        'tanggal' => 'date',
        'released_io_at' => 'datetime',
    ];

    public $timestamps = false;

    public function aset()
    {
        return $this->hasMany(JadwalMobilisasiAset::class, 'id_mobilisasi')
            ->where('is_active', true);
    }
}
