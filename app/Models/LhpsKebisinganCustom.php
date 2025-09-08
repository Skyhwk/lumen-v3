<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganCustom extends Sector
{
    protected $table = "lhps_kebisingan_custom";
    public $timestamps = false;

    protected $guarded = [];
}