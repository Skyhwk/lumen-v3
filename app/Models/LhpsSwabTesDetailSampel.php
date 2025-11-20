<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesDetailSampel extends Sector
{
    protected $table = "lhps_swab_tes_detail_sampel";
    public $timestamps = false;

    protected $guarded = [];
}