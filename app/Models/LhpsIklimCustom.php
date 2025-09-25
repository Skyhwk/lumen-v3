<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimCustom extends Sector
{
    protected $table = "lhps_iklim_custom";
    public $timestamps = false;

    protected $guarded = [];
}