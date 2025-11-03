<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueErgonomi extends Sector
{
    protected $table = "ws_value_ergonomi";
    public $timestamps = false;

    protected $guarded = [];
}