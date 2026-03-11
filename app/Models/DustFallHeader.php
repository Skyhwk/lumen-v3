<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DustFallHeader extends Sector{

    protected $table = 'dustfall_header';
    public $timestamps = false;

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function ws_udara()
    {
        return $this->hasOne('App\Models\WsValueUdara'::class, 'id_dustfall_header', 'id');
    }

    public function order_detail()
    {
        return $this->hasOne('App\Models\OrderDetail'::class, 'no_sampel', 'no_sampel');
    }

    public function ws_value()
    {
        return $this->hasOne('App\Models\WsValueLingkungan'::class, 'dustfall_header_id', 'id');
    }
}