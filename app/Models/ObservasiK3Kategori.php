<?php

namespace App\Models;

class ObservasiK3Kategori extends Sector
{
    protected $table = 'observasi_k3_kategori';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(ObservasiK3Template::class, 'observasi_k3_template_id');
    }
}
