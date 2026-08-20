<?php

namespace App\Models;

use App\Models\Sector;

class CatatanLhpRilis extends Sector
{
    protected $table = "catatan_lhp_rilis";
    public $timestamps = false;
    protected $guarded = [];
}
