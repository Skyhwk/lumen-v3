<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\MasterCabang;

class ScanSampelTc extends Sector
{
    protected $connection = 'lims';

    protected $table = "scan_sampel_tc";
    public $timestamps = false;
    protected $guarded = [];

}
