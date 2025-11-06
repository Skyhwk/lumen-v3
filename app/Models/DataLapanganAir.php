<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganAir extends Sector
{
    protected $table = "data_lapangan_air";
    public $timestamps = false;

    protected $guarded = [];

    public function detail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')->where('is_active', true);
        // ->where('is_active', true);
    }
}
