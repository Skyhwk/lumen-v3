<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimDetail extends Sector
{
    protected $table = "lhps_iklim_detail";
    public $timestamps = false;

    protected $guarded = [];
}