<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsIklimHeaderHistory extends Sector
{
    protected $table = "lhps_iklim_header_history";
    public $timestamps = false;

    protected $guarded = [];
 
}