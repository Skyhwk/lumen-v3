<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesDetailParameterHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_swab_tes_detail_parameter_history";
    public $timestamps = false;

    protected $guarded = [];
}