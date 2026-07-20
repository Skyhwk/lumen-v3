<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class lhpsSinarUVCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_sinaruv_custom";
    public $timestamps = false;

    protected $guarded = [];
}