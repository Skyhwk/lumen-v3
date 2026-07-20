<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganSARHeader extends Sector
{
    public static $useLimsDetail = false;

    protected $table = "datalapangan_sar_header";
    public $timestamps = false;

    protected $guarded = [];
}