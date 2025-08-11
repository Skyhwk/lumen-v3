<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DirectLainHeader extends Sector
{

    protected $table = 'directlain_header';
    public $timestamps = false;

    protected $guarded = [];

    public function ws_udara()
    {
        return $this->belongsTo('App\Models\WsValueUdara', 'id', 'id_direct_lain_header');
    }

}