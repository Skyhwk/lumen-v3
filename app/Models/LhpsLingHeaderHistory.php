<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsLingHeaderHistory extends Sector
{
    protected $table = "lhps_ling_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}