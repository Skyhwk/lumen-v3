<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanDetailHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_air_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}