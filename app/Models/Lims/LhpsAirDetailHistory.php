<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsAirDetailHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_air_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}