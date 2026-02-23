<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DebuPersonalHeader extends Sector{

    protected $table = 'debu_personal_header';
    public $timestamps = false;

    protected $guarded = [];

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function ws_value()
    {
        return $this->hasOne(WsValueLingkungan::class, 'debu_personal_header_id', 'id');
    }

    // public function ws_udara()
    // {
    //     return $this->belongsTo('App\Models\WsValueUdara', 'no_sampel', 'no_sampel');
    // }
    public function ws_udara()
    {
        return $this->hasOne(WsValueUdara::class, 'id_debu_personal_header', 'id');
    }

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }

    public function data_lapangan()
    {
        return $this->belongsTo('App\Models\DataLapanganDebuPersonal', 'no_sampel', 'no_sampel');
    }

    public function ws_lingkungan()
    {
        return $this->belongsTo('App\Models\WsValueLingkungan', 'no_sampel', 'no_sampel');
    }
}
