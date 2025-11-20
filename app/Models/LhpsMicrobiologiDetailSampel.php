<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMicrobiologiDetailSampel extends Sector
{
    protected $table = "lhps_microbiologi_detail_sampel";
    public $timestamps = false;

    protected $guarded = [];
}