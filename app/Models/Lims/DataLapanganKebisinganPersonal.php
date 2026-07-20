<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganKebisinganPersonal extends Sector
{
    
    protected $connection = 'lims';
protected $table = "data_lapangan_kebisingan_personal";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
}