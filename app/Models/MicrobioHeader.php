<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MicrobioHeader extends Sector{

    protected $table = 'microbio_header';
    public $timestamps = false;

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel');
    }

    public function detail_lapangan()
    {
        return $this->belongsTo(DetailMicrobiologi::class, 'no_sampel', 'no_sampel');
    }

    public function ws_value()
    {
        return $this->hasOne(WsValueUdara::class, 'id_microbiologi_header', 'id');
    }
    
    public function ws_udara()
    {
        return $this->hasOne(WsValueUdara::class, 'id_microbiologi_header', 'id');
    }
}
