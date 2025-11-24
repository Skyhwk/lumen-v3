<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiIsokinetikDetailHistory extends Sector
{
    protected $table = "lhps_emisi_isokinetik_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}
