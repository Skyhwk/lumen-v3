<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMDetailHistory extends Sector
{
    protected $table = "lhps_medanlm_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}