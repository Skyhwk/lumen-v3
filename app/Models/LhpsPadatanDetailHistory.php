<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanDetailHistory extends Sector
{
    protected $table = "lhps_air_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}