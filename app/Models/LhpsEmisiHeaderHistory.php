<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsEmisiHeaderHistory extends Sector
{
    protected $table = "lhps_emisi_header_history";
    public $timestamps = false;

    protected $guarded = [];
}