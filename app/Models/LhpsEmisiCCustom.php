<?php

namespace App\Models;

use App\Models\Sector;

class LhpsEmisiCCustom extends Sector
{
    protected $table = "lhps_emisic_custom";
    public $timestamps = false;

    protected $guarded = ['id'];
}
