<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingDetail extends Sector
{
    protected $table = "lhps_ling_detail";
    public $timestamps = false;

    protected $guarded = [];
}