<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingCustom extends Sector
{
    protected $table = "lhps_ling_custom";
    public $timestamps = false;

    protected $guarded = [];
}