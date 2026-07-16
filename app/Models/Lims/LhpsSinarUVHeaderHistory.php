<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsSinarUVHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_sinaruv_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}