<?php

namespace App\Models;

class JadwalMobilisasi extends Sector
{
    protected $table = 'jadwal_mobilisasi';

    protected $guarded = [];

    protected $casts = [
        'batch_jadwal_ids' => 'array',
        'peralatan_sampling' => 'array',
        'is_active' => 'boolean',
        'tanggal' => 'date',
        'released_io_at' => 'datetime',
    ];

    public function aset()
    {
        return $this->hasMany(JadwalMobilisasiAset::class, 'id_mobilisasi')
            ->where('is_active', true);
    }
}
