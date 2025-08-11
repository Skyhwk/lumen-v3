<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganDetail extends Sector
{
    protected $table = "lhps_kebisingan_detail";
    public $timestamps = false;

    protected $guarded = [];
}