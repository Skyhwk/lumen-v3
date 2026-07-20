<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganDirectLain extends Sector
{
    protected $connection = 'lims';

    protected $table = "data_lapangan_direct_lain";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo('App\Models\Lims\OrderDetail', 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
}