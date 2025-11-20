<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMicrobiologiDetailHistory extends Sector
{
    protected $table = "lhps_microbiologi_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}