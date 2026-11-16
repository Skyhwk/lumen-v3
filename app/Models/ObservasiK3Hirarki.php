<?php

namespace App\Models;

class ObservasiK3Hirarki extends Sector
{
    protected $table = 'observasi_k3_hirarki';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ObservasiK3Item::class, 'observasi_k3_hirarki_id')->orderBy('urutan');
    }
}
