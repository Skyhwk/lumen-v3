<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganIsokinetikPenentuanPartikulat extends Sector
{
    protected $table = "data_lapangan_isokinetik_penentuan_partikulat";
    public $timestamps = false;

    protected $guarded = [];
    
    protected $casts = [
        'DGM' => 'array',
        'Filter' => 'array',
        'Meter' => 'array',
        'Oven' => 'array',
        'PaPs' => 'array',
        'Probe' => 'array',
        'Vp' => 'array',
        'dH' => 'array',
        'dP' => 'array',
        'data_total_vs' => 'array',
        'delta_vm' => 'array',
        'Stack' => 'array',
        'exit_impinger' => 'array',
        'sebelumpengujian' => 'array',
        'sesudahpengujian' => 'array',
    ];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function survei(){
        return $this->belongsTo(DataLapanganIsokinetikSurveiLapangan::class, 'id_lapangan', 'id')->where('is_active', true);
    }

    public function method2(){
        return $this->belongsTo(DataLapanganIsokinetikPenentuanKecepatanLinier::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }
    public function method3(){
        return $this->belongsTo(DataLapanganIsokinetikBeratMolekul::class, 'no_sampel', 'no_sampel');
    }
    
}