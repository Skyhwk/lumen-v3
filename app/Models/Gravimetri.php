<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Gravimetri extends Sector
{
    protected $table = "gravimetri";
    public $timestamps = false;

    protected $guarded = [];

    public function ws_value()
    {
        return $this->belongsTo('App\Models\WsValueAir', 'id', 'id_gravimetri')->where('is_active', true);
    }

    public function ws_value_retest()
    {
        return $this->belongsTo('App\Models\WsValueAir', 'id', 'id_gravimetri');
    }

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function master_parameter_air()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('id_kategori', 1)->where('is_active', true);
    }

    public function master_parameter_padatan()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('id_kategori', 6)->where('is_active', true);
    }
    public function baku_mutu()
    {
        return $this->belongsTo('App\Models\MasterBakumutu', 'parameter', 'parameter');
    }

    public function createdByKaryawan()
    {
        return $this->belongsTo('App\Models\MasterKaryawan', 'created_by', 'nama_lengkap');
    }
}