<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueMicrobio extends Sector
{
    protected $table = "ws_value_microbio";
    public $timestamps = false;

    protected $guarded = [];
}