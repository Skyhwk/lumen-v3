<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSinarUVDetailHistory extends Sector
{
    protected $table = "lhps_sinaruv_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}