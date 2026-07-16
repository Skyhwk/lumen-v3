<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_air_custom";
    public $timestamps = false;

    protected $guarded = [];
}