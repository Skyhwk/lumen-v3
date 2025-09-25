<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMCustom extends Sector
{
    protected $table = "lhps_medanlm_custom";
    public $timestamps = false;

    protected $guarded = [];
}