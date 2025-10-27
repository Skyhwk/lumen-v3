<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanCustom extends Sector
{
    protected $table = "lhps_pencahayaan_custom";
    public $timestamps = false;

    protected $guarded = [];
}
