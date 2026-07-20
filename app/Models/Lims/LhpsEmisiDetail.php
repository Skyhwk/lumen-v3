<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_emisi_detail";
    public $timestamps = false;

    protected $guarded = [];
}