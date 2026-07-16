<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSinarUVDetailHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_sinaruv_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}