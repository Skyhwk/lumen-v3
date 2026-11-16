<?php

namespace App\Models;

class ObservasiK3Item extends Sector
{
    protected $table = 'observasi_k3_item';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'config' => 'array',
        'wajib_keterangan' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function hirarki()
    {
        return $this->belongsTo(ObservasiK3Hirarki::class, 'observasi_k3_hirarki_id');
    }
}
