<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanDetail extends Sector
{
    protected $table = "lhps_padatan_detail";
    public $timestamps = false;

    protected $guarded = [];
}
