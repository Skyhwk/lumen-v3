<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganPersonalCustom extends Sector
{
    protected $table = "lhps_kebisingan_personal_custom";
    public $timestamps = false;

    protected $guarded = [];
}