<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMDetail extends Sector
{
    protected $table = "lhps_medanlm_detail";
    public $timestamps = false;

    protected $guarded = [];
}