<?php

namespace App\Models\Lims;

use App\Models\Sector;

class LhpsEmisiCCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisic_custom";
    public $timestamps = false;

    protected $guarded = ['id'];
}
