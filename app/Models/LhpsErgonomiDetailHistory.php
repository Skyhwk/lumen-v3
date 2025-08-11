<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsErgonomiDetailHistory extends Sector
{
    protected $table = "lhps_ergonomi_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}