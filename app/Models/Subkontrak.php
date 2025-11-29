<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Subkontrak extends Sector
{
    protected $table = "subkontrak";
    public $timestamps = false;

    protected $guarded = [];

    // protected $appends = ['hasil'];

    public function ws_value()
    {
        return $this->belongsTo('App\Models\WsValueAir', 'id', 'id_subkontrak')->where('is_active', true);
    }

    public function ws_value_retest()
    {
        return $this->belongsTo('App\Models\WsValueAir', 'id', 'id_subkontrak');
    }

    public function getHasilAttribute()
    {
        return $this->ws_value->hasil;
    }

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function master_parameter()
    {
        return \App\Models\Parameter::where('nama_lab', $this->parameter)
            ->whereIn('id_kategori', [1, 6])
            ->where('is_active', true)
            ->first();
    }

    public function parameter_udara()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('is_active', true)->where('id_kategori', 4);
    }
    public function parameter_emisi()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('is_active', true)->where('id_kategori', 5);
    }
    public function category()
    {
        return $this->belongsTo('App\Models\MasterKategori', 'category_id', 'id');
    }

    public function createdByKaryawan()
    {
        return $this->belongsTo('App\Models\MasterKaryawan', 'created_by', 'nama_lengkap');
    }

    public function ws_value_linkungan()
    {
        return $this->belongsTo('App\Models\WsValueLingkungan', 'id', 'id_subkontrak')->where('is_active', true);
    }
    
    public function ws_udara()
    {
        return $this->belongsTo('App\Models\WsValueUdara', 'id', 'id_subkontrak')->where('is_active', true);
    }
    public function ws_value_cerobong()
    {
        return $this->belongsTo('App\Models\WsValueEmisiCerobong', 'id', 'id_subkontrak');
    }

    public function baku_mutu()
    {
        return $this->belongsTo('App\Models\MasterBakumutu', 'parameter', 'parameter');
    }

    public function TrackingSatu(){
        return $this->belongsTo('App\Models\Ftc', 'no_sampel', 'no_sample')->where('is_active', true);
    }

    public function TrackingDua(){
        return $this->belongsTo('App\Models\FtcT', 'no_sampel', 'no_sample')->where('is_active', true);
    }

    public function detail_lapangan_microbiologi()
    {
        return $this->belongsTo(DetailMicrobiologi::class, 'no_sampel', 'no_sampel');
    }

}
