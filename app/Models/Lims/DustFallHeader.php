<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DustFallHeader extends Sector{
    protected $connection = 'lims';


    protected $table = 'dustfall_header';
    public $timestamps = false;

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Lims\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function ws_udara()
    {
        return $this->hasOne('App\Models\Lims\WsValueUdara'::class, 'id_dustfall_header', 'id');
    }

    public function order_detail()
    {
        return $this->hasOne('App\Models\Lims\OrderDetail'::class, 'no_sampel', 'no_sampel');
    }

    public function ws_value()
    {
        return $this->hasOne('App\Models\Lims\WsValueLingkungan'::class, 'dustfall_header_id', 'id');
    }
}