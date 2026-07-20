<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanCustom extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_pencahayaan_custom";
    public $timestamps = false;

    protected $guarded = [];
}
