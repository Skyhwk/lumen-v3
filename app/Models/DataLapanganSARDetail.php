<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganSARDetail extends Sector
{
    protected $table = "datalapangan_sar_detail";
    public $timestamps = false;

    protected $guarded = [];
}