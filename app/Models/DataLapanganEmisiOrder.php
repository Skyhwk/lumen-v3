<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganEmisiOrder extends Sector
{
    protected $table = "data_lapangan_emisi_order";
    public $timestamps = false;


    public function regulasi(){
        return $this->belongsTo(MasterRegulasi::class, 'id_regulasi', 'id')->with('bakumutu')->where('is_active', true);
    }

    public function qr(){
        return $this->belongsTo(MasterQr::class, 'id_qr', 'id')->where('is_active', true);
    }

    public function kendaraan(){
        return $this->belongsTo(MasterKendaraan::class, 'id_kendaraan','id')->where('is_active', true);
    }

    public function fdl(){
        return $this->hasOne(DataLapanganEmisiKendaraan::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }
}
