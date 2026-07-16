<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMicrobiologiDetailParameter extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_microbiologi_detail_parameter";
    public $timestamps = false;

    protected $guarded = [];
}