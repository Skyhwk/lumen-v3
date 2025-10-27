<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirDetail extends Sector
{
    protected $table = "lhps_air_detail";
    public $timestamps = false;

    protected $guarded = [];
}