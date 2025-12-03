<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganErgonomi extends Sector
{
    protected $table = "data_lapangan_ergonomi";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->with('orderHeader')->where('is_active', true);
    }
   
}