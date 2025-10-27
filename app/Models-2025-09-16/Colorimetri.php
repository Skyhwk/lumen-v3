<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Colorimetri extends Sector
{
    protected $table = "colorimetri";
    public $timestamps = false;

    protected $guarded = [];

    public function ws_value() {
        return $this->belongsTo('App\Models\WsValueAir', 'id', 'id_colorimetri')->where('is_active', true);
    }

    public function ws_value_retest() {
        return $this->belongsTo('App\Models\WsValueAir', 'id', 'id_colorimetri');
    }

    public function order_detail() {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function master_parameter() {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('id_kategori', 1)->where('is_active', true);
    }

    public function baku_mutu() {
        return $this->belongsTo('App\Models\MasterBakumutu', 'parameter', 'parameter');
    }
}