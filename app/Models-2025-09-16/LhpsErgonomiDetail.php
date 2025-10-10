<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsErgonomiDetail extends Sector
{
    protected $table = "lhps_ergonomi_detail";
    public $timestamps = false;

    protected $guarded = [];
}