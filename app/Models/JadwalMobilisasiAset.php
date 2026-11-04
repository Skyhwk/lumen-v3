<?php

namespace App\Models;

class JadwalMobilisasiAset extends Sector
{
    protected $table = 'jadwal_mobilisasi_aset';

    protected $guarded = [];

    protected $casts = [
        'id_mobilisasi' => 'integer',
        'id_aset' => 'integer',
        'kategori_aset_id' => 'integer',
        'sub_kategori_aset_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function mobilisasi()
    {
        return $this->belongsTo(JadwalMobilisasi::class, 'id_mobilisasi');
    }

    public function aset()
    {
        return $this->belongsTo(GaAsset::class, 'id_aset');
    }
}
