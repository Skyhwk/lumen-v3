<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganIsokinetikKadarAir extends Sector
{
    protected $table = "data_lapangan_isokinetik_kadar_air";
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'data_impinger' => 'array',
        'data_dgmterbaca' => 'array',
        'data_dgm_test' => 'array',
        'data_kalkulasi_dgm' => 'array',
    ];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function survei(){
        return $this->belongsTo(DataLapanganIsokinetikSurveiLapangan::class, 'id_lapangan', 'id')->where('is_active', true);
    }
}