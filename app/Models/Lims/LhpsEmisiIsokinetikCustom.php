<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiIsokinetikCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisi_isokinetik_custom";
    public $timestamps = false;

    protected $guarded = [];
}
