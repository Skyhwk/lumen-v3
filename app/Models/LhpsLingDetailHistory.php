<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingDetailHistory extends Sector
{
    protected $table = "lhps_ling_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}