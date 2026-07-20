<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganErgonomi extends Sector
{
protected $table = "data_lapangan_ergonomi";
    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        if (config('is_lims', false)) {
            return $this->belongsTo(\App\Models\Lims\OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active', true);
        }
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }
   
}