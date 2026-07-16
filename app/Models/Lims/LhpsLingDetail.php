<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_ling_detail";
    public $timestamps = false;

    protected $guarded = [];
}