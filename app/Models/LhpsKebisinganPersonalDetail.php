<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganPersonalDetail extends Sector
{
    protected $table = "lhps_kebisingan_personal_detail";
    public $timestamps = false;

    protected $guarded = [];
}