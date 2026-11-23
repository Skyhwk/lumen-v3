<?php

namespace App\Models;

class IoPeralatanTim extends Sector
{
    protected $table = 'io_peralatan_tim';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date',
        'batch_ids' => 'array',
        'peralatan' => 'array',
        'snapshots' => 'array',
        'is_active' => 'boolean',
    ];

    public $timestamps = false;
}
