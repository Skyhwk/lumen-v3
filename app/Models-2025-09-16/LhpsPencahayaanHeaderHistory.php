<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanHeaderHistory extends Sector
{
    protected $table = "lhps_pencahayaan_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}