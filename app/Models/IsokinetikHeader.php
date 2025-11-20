<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class IsokinetikHeader extends Sector{

    protected $table = 'isokinetik_header';
    public $timestamps = false;

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }
    
    public function method1()
    {
        return $this->belongsTo('App\Models\DataLapanganIsokinetikSurveiLapangan', 'id_lapangan', 'id');
    }

    public function method2()
    {
        return $this->belongsTo('App\Models\DataLapanganIsokinetikPenentuanKecepatanLinier', 'id_lapangan', 'id_lapangan');
    }

    public function method3()
    {
        return $this->belongsTo('App\Models\DataLapanganIsokinetikBeratMolekul', 'no_sampel', 'no_sampel');
    }

    public function method4()
    {
        return $this->belongsTo('App\Models\DataLapanganIsokinetikKadarAir', 'id_lapangan', 'id_lapangan');
    }

    public function method5()
    {
        return $this->belongsTo('App\Models\DataLapanganIsokinetikPenentuanPartikulat', 'id_lapangan', 'id_lapangan');
    }

    public function method6()
    {
        return $this->belongsTo('App\Models\DataLapanganIsokinetikHasil', 'id_lapangan', 'id_lapangan');
    }

    public function ws_value_cerobong(){
        return $this->belongsTo('App\Models\WsValueEmisiCerobong', 'id', 'id_isokinetik');
    }
    
    public function parameter_emisi()
    {
        return $this->belongsTo('App\Models\Parameter', 'parameter', 'nama_lab')->where('is_active', true)->where('id_kategori', 5);
    }
}