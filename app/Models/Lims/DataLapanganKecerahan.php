<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganKecerahan extends Sector
{
    
    protected $connection = 'lims';
protected $table = "data_lapangan_kecerahan";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
}