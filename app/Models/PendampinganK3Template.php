<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PendampinganK3Template extends Sector{

    protected $table = 'pendampingan_k3_template';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'data' => 'object',
    ];

}
