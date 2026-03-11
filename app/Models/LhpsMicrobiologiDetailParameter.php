<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMicrobiologiDetailParameter extends Sector
{
    protected $table = "lhps_microbiologi_detail_parameter";
    public $timestamps = false;

    protected $guarded = [];
}