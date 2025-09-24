<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSinarUVDetail extends Sector
{
    protected $table = "lhps_sinaruv_detail";
    public $timestamps = false;

    protected $guarded = [];
}