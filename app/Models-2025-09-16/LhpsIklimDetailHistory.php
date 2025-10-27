<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimDetailHistory extends Sector
{
    protected $table = "lhps_iklim_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}