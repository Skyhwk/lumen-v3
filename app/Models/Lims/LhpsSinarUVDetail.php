<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSinarUVDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_sinaruv_detail";
    public $timestamps = false;

    protected $guarded = [];
}