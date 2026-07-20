<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_medanlm_detail";
    public $timestamps = false;

    protected $guarded = [];
}