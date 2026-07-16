<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiCDetailHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisic_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}