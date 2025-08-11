<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanDetailHistory extends Sector
{
    protected $table = "lhps_pencahayaan_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}