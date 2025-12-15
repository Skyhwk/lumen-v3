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
     public function baku_mutu()
    {
        return $this->belongsTo('App\Models\MasterBakumutu', 'parameter', 'parameter');
    }

    public function ws_value_linkungan()
    {
        return $this->belongsTo('App\Models\WsValueLingkungan', 'id', 'lingkungan_header_id')->with('dataLapanganLingkunganHidup', 'dataLapanganLingkunganKerja', 'detailLingkunganHidup', 'detailLingkunganKerja')->where('is_active', true);
    }

    public function parameter_udara()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('id_kategori', 4)->where('is_active', true);
    }
}