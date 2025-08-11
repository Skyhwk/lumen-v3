<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiCDetailHistory extends Sector
{
    protected $table = "lhps_emisic_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}