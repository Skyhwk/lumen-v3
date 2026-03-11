<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsPadatanHeaderHistory extends Sector
{
    protected $table = "lhps_padatan_header_history";
    public $timestamps = false;

    protected $guarded = [];
}