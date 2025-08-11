<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranDetail extends Sector
{
    protected $table = "lhps_getaran_detail";
    public $timestamps = false;

    protected $guarded = [];
}