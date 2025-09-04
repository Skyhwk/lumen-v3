<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranCustom extends Sector
{
    protected $table = "lhps_getaran_custom";
    public $timestamps = false;

    protected $guarded = [];
}