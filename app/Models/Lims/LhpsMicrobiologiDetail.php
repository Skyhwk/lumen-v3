<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMicrobiologiDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_microbiologi_detail";
    public $timestamps = false;

    protected $guarded = [];
}