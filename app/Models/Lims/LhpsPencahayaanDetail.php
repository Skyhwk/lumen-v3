<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPencahayaanDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_pencahayaan_detail";
    public $timestamps = false;

    protected $guarded = [];
}