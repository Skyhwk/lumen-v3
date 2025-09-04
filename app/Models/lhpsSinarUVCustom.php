<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class lhpsSinarUVCustom extends Sector
{
    protected $table = "lhps_sinaruv_custom";
    public $timestamps = false;

    protected $guarded = [];
}