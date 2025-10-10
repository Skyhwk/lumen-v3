<?php

namespace App\Models;

use App\Models\Sector;

class SampelDiantarDetail extends Sector
{
    protected $table = 'sampel_diantar_detail';
    protected $guarded = ['id'];
    public $timestamps = false;
}
