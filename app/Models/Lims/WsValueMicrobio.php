<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueMicrobio extends Sector
{
    
    protected $connection = 'lims';
protected $table = "ws_value_microbio";
    public $timestamps = false;

    protected $guarded = [];
}