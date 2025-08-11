<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\MasterCabang;

class ScanSampelTc extends Sector
{
    protected $table = "scan_sampel_tc";
    public $timestamps = false;
    protected $guarded = [];

}
