<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsGetaranHeaderHistory extends Sector
{
    protected $table = "lhps_getaran_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}