<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class EmisiCerobongHeader extends Sector
{
    protected $table = 'emisi_cerobong_header';
    public $timestamps = false;

    protected $guarded = [];

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function order_detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function ws_value_cerobong()
    {
        return $this->belongsTo('App\Models\WsValueEmisiCerobong', 'id', 'id_emisi_cerobong_header')->where('is_active', true);
    }
    
    public function ws_value()
    {
        return $this->belongsTo('App\Models\WsValueEmisiCerobong', 'id', 'id_emisi_cerobong_header')->where('is_active', true);
    }

    public function ws_value_one(){
        return $this->hasOne('App\Models\WsValueEmisiCerobong', 'id_emisi_cerobong_header', 'id')->where('is_active', true);
    }

    public function parameter()
    {
        return $this->belongsTo('App\Models\Parameter', 'id_parameter', 'id')->where('is_active', true);
    }
    public function master_parameter()
    {
        return $this->belongsTo('App\Models\Parameter', 'id_parameter', 'id')->where('is_active', true);
    }
    public function data_lapangan()
    {
        return $this->belongsTo('App\Models\DataLapanganEmisiCerobong', 'no_sampel', 'no_sampel');
    }

      public function parameter_emisi()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('is_active', true)->where('id_kategori', 5);
    }
}