<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranHeaderHistory extends Sector
{
    protected $connection = 'lims';

    protected $table = "lhps_getaran_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}