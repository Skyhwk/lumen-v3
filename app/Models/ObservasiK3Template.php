<?php

namespace App\Models;

class ObservasiK3Template extends Sector
{
    protected $table = 'observasi_k3_template';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function kategori()
    {
        return $this->hasMany(ObservasiK3Kategori::class, 'observasi_k3_template_id');
    }

    public function hirarki()
    {
        return $this->hasMany(ObservasiK3Hirarki::class, 'observasi_k3_template_id')->orderBy('urutan');
    }
}
