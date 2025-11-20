<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesDetailParameter extends Sector
{
    protected $table = "lhps_swab_tes_detail_parameter";
    public $timestamps = false;

    protected $guarded = [];
}