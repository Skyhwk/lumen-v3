<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganIsokinetikBeratMolekul extends Sector
{
    
    protected $connection = 'lims';
protected $table = "data_lapangan_isokinetik_berat_molekul";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
    
    public function survei(){
        return $this->belongsTo(DataLapanganIsokinetikSurveiLapangan::class, 'id_lapangan', 'id')->where('is_active', true);
    }
}