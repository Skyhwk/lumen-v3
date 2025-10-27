<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueSwab extends Sector
{
    protected $table = "ws_value_swab";
    public $timestamps = false;

    protected $guarded = [];
}