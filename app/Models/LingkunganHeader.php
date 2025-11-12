<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LingkunganHeader extends Sector
{

    protected $table = 'lingkungan_header';
    public $timestamps = false;
    protected $guarded = [];

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function ws_value() {
        return $this->belongsTo('App\Models\WsValueLingkungan', 'id', 'lingkungan_header_id')->where('is_active', true);
    }

    public function ws_udara()
    {
        return $this->belongsTo('App\Models\WsValueUdara', 'id', 'id_lingkungan_header');
    }

    public function ws_value_linkungan()
    {
        return $this->belongsTo('App\Models\WsValueLingkungan', 'id', 'lingkungan_header_id')->with('dataLapanganLingkunganHidup', 'dataLapanganLingkunganKerja', 'detailLingkunganHidup', 'detailLingkunganKerja')->where('is_active', true);
    }

    public function master_parameter()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('id_kategori', 4)->where('is_active', true);
    }
    public function parameter_udara()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('id_kategori', 4)->where('is_active', true);
    }
         public function baku_mutu()
    {
        return $this->belongsTo('App\Models\MasterBakumutu', 'parameter', 'parameter');
    }
}
