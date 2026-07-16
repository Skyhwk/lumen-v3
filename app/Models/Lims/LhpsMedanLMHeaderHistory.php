<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsMedanLMHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_medanlm_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}