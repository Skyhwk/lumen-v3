<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganEmisiCerobong extends Sector
{
    protected $table = "data_lapangan_emisi_cerobong";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
    // public function wsValueCerobong() {
    //     return $this->belongsTo('App\Models\WsValueEmisiCerobong', 'no_sampel', 'no_sampel');
    // }
    // public function emisi_cerobong_header() {
    //     return $this->belongsTo('App\Models\EmisiCerobongHeader', 'no_sampel', 'no_sampel')->where('is_active', true);
    // }
}