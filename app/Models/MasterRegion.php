<?php

namespace App\Models;

use App\Models\Sector;

class MasterRegion extends Sector
{
    protected $table = "master_region";
    public $timestamps = false;
    protected $guarded = [];
}
