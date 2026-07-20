<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingDetailHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_ling_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}