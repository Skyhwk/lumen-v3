<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class IklimHeader extends Sector
{
    protected $table = "isbb_header";
    public $timestamps = false;

    protected $guarded = [];
    public function iklim_panas()
    {
        return $this->belongsTo('App\Models\DataLapanganIklimPanas', 'no_sampel', 'no_sampel');
    }
    public function iklim_dingin()
    {
        return $this->belongsTo('App\Models\DataLapanganIklimDingin', 'no_sampel', 'no_sampel');
    }
    public function ws_udara()
    {
        return $this->belongsTo('App\Models\WsValueUdara', 'no_sampel', 'no_sampel');
    }
    public function orderDetail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
}