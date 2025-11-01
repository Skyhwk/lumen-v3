<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganIklimPanas extends Sector
{
    protected $table = "data_lapangan_iklim_panas";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }
}