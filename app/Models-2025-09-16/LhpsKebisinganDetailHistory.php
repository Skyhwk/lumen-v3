<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LhpsKebisinganDetailHistory extends Sector
{
    protected $table = "lhps_kebisingan_detail_history";
    public $timestamps = false;

    protected $guarded = [];
}