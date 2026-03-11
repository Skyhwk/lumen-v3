<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanCustom extends Sector
{
    protected $table = "lhps_padatan_custom";
    public $timestamps = false;

    protected $guarded = [];
}