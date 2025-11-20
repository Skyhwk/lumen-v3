<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesDetailSampelHistory extends Sector
{
    protected $table = "lhps_swab_tes_detail_sampel_history";
    public $timestamps = false;

    protected $guarded = [];
}