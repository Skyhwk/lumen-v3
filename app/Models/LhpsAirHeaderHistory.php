<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirHeaderHistory extends Sector
{
    protected $table = "lhps_air_header_history";
    public $timestamps = false;

    protected $guarded = [];
}