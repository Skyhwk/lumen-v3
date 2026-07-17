<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganDebuPersonal extends Sector
{
    protected $connection = 'lims';

    protected $table = "data_lapangan_debu_personal";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo('App\Models\Lims\OrderDetail', 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
}