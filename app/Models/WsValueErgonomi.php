<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueErgonomi extends Sector
{
    protected $table = "ws_value_ergonomi";
    public $timestamps = false;

    protected $guarded = [];
    public function detail(){
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
    }
    public function lapangan(){
        return $this->belongsTo('App\Models\DataLapanganErgonomi', 'id_data_lapangan', 'id');
    }
}