<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSwabTesCustomSampel extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_swab_tes_custom_sampel";
    public $timestamps = false;

    protected $guarded = [];
}