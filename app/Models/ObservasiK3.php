<?php

namespace App\Models;

class ObservasiK3 extends Sector
{
    protected $table = 'observasi_k3';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'nomor_sampel' => 'array',
        'lokasi' => 'array',
        'kategori_terpilih' => 'array',
        'form_jawaban' => 'array',
        'foto' => 'array',
        'snapshot_pengesahan' => 'array',
        'is_active' => 'boolean',
        'is_pengesahan_selesai' => 'boolean',
        'is_emailed' => 'boolean',
    ];
}
