<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_air_detail";
    public $timestamps = false;

    protected $guarded = [];
}