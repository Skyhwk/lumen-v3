<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PendampinganK3 extends Sector
{

    protected $table = 'pendampingan_k3';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'foto'        => 'array',
        'nomor_sampel' => 'array',
        'lokasi'      => 'array',
        'templates'   => 'array',
    ];
}
