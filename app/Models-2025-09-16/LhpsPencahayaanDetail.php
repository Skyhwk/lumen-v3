<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanDetail extends Sector
{
    protected $table = "lhps_pencahayaan_detail";
    public $timestamps = false;

    protected $guarded = [];
}