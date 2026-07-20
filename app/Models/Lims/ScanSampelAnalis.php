<?php

namespace App\Models\Lims;

use App\Models\Sector;

class ScanSampelAnalis extends Sector
{
    protected $connection = 'lims';

    protected $table = 'scan_sampel_analis';

    public $timestamps = false;
    protected $guarded = [];
   
}