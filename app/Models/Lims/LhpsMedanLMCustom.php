<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_medanlm_custom";
    public $timestamps = false;

    protected $guarded = [];
}