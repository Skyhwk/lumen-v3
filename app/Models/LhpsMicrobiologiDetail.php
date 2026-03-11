<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMicrobiologiDetail extends Sector
{
    protected $table = "lhps_microbiologi_detail";
    public $timestamps = false;

    protected $guarded = [];
}