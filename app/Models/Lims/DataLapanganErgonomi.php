<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganErgonomi extends Sector
{
    protected $connection = 'lims';

    protected $table = "data_lapangan_ergonomi";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo('App\Models\Lims\OrderDetail', 'no_sampel', 'no_sampel')->with('orderHeader')->where('is_active', true);
    }
   
}