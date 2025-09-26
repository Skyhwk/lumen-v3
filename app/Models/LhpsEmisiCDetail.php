<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiCDetail extends Sector
{
    protected $table = "lhps_emisic_detail";
    public $timestamps = false;

    protected $guarded = [];
}