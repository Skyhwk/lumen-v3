<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesCustomParameter extends Sector
{
    protected $table = "lhps_swab_tes_custom_parameter";
    public $timestamps = false;

    protected $guarded = [];
}