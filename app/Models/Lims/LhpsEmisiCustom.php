<?php

namespace App\Models\Lims;

use App\Models\Sector;

class LhpsEmisiCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisi_custom";
    public $timestamps = false;

    protected $guarded = ['id'];
}
