<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueLingkungan extends Sector
{
    protected $table = "ws_value_lingkungan";
    public $timestamps = false;

    protected $guarded = [];
    public function dataLapanganLingkunganHidup() {
        return $this->belongsTo('App\Models\DataLapanganLingkunganHidup', 'no_sampel', 'no_sampel');
    }
    public function dataLapanganLingkunganKerja() {
        return $this->belongsTo('App\Models\DataLapanganLingkunganKerja', 'no_sampel', 'no_sampel');
    }
    public function detailLingkunganHidup() {
        return $this->belongsTo('App\Models\DetailLingkunganHidup', 'no_sampel', 'no_sampel');
    }
    public function detailLingkunganKerja() {
        return $this->belongsTo('App\Models\DetailLingkunganKerja', 'no_sampel', 'no_sampel');
    }
}