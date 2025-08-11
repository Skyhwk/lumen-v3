<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMHeaderHistory extends Sector
{
    protected $table = "lhps_medanlm_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}