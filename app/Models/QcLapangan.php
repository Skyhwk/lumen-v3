<?php

namespace App\Models;

use App\Models\Sector;

class QcLapangan extends Sector
{
    protected $table = "qc_lapangan";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }

    public function OrderDetail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel');
    }
   
}