<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganIsokinetikHasil extends Sector
{
    protected $table = "data_lapangan_isokinetik_hasil";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function method5(){
        return $this->belongsTo(DataLapanganIsokinetikPenentuanPartikulat::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
    
    public function method3(){
        return $this->belongsTo(DataLapanganIsokinetikBeratMolekul::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function survei(){
        return $this->belongsTo(DataLapanganIsokinetikSurveiLapangan::class, 'id_lapangan', 'id')->where('is_active', true);
    }
}