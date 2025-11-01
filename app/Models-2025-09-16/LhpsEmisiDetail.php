<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiDetail extends Sector
{
    protected $table = "lhps_emisi_detail";
    public $timestamps = false;

    protected $guarded = [];
}