<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesCustomSampelParameter extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_swab_tes_custom_parameter";
    public $timestamps = false;

    protected $guarded = [];
}