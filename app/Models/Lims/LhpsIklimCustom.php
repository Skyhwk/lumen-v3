<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_iklim_custom";
    public $timestamps = false;

    protected $guarded = [];
}