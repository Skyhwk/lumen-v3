<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiCDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisic_detail";
    public $timestamps = false;

    protected $guarded = [];
}