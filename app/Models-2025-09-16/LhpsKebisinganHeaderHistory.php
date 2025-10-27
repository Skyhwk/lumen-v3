<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganHeaderHistory extends Sector
{
    protected $table = "lhps_kebisingan_header_history";
    public $timestamps = false;

    protected $guarded = [];

 
}