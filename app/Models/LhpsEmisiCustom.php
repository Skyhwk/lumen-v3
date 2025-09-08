<?php

namespace App\Models;

use App\Models\Sector;

class LhpsEmisiCustom extends Sector
{
    protected $table = "lhps_emisi_custom";
    public $timestamps = false;

    protected $guarded = ['id'];
}
