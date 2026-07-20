<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_air_header_history";
    public $timestamps = false;

    protected $guarded = [];
}