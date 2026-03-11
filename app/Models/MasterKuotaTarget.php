<?php

namespace App\Models;

use App\Models\Sector;

class MasterKuotaTarget extends Sector
{
    protected $table = 'master_kuota_target';
    protected $guarded = ['id'];
    public $timestamps = false;

    protected $casts = [
        'kuota' => 'array',
    ];
}
