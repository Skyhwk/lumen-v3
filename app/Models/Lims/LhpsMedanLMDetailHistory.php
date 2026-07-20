<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMDetailHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_medanlm_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}