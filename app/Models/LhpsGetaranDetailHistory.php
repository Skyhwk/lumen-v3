<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranDetailHistory extends Sector
{
    protected $table = "lhps_getaran_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}