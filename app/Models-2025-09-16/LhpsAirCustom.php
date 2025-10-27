<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirCustom extends Sector
{
    protected $table = "lhps_air_custom";
    public $timestamps = false;

    protected $guarded = [];
}