<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KebisinganHeader extends Sector{

    protected $table = 'kebisingan_header';
    public $timestamps = false;

    protected $guarded = [];
    public function ws_udara() {
        return $this->belongsTo('App\Models\WsValueUdara', 'no_sampel', 'no_sampel');
    }

    public function data_lapangan() {
        return $this->belongsTo('App\Models\DataLapanganKebisingan', 'no_sampel', 'no_sampel');
    }

    public function data_lapangan_personal()
    {
        return $this->belongsTo('App\Models\DataLapanganKebisinganPersonal', 'no_sampel', 'no_sampel');
    }
    
    public function orderDetail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
}