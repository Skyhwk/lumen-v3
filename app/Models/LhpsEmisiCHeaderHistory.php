<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiCHeaderHistory extends Sector
{
    protected $table = "lhps_emisic_header_history";
    public $timestamps = false;

    protected $guarded = [];
}