<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiIsokinetikCustom extends Sector
{
    protected $table = "lhps_emisi_isokinetik_custom";
    public $timestamps = false;

    protected $guarded = [];
}
