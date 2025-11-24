<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiIsokinetikDetail extends Sector
{
    protected $table = "lhps_emisi_isokinetik_detail";
    public $timestamps = false;

    protected $guarded = [];
}