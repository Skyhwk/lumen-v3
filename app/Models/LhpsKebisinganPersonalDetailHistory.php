<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganPersonalDetailHistory extends Sector
{
    protected $table = "lhps_kebisingan_personal_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}