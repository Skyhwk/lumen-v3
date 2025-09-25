<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganIsokinetikPenentuanKecepatanLinier extends Sector
{
    protected $table = "data_lapangan_isokinetik_penentuan_kecepatan_linier";
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'dataDp' => 'array',
        'uji_aliran' => 'array'
    ];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function survei(){
        return $this->belongsTo(DataLapanganIsokinetikSurveiLapangan::class, 'id_lapangan', 'id')->where('is_active', true);
    }
}