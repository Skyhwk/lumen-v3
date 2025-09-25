<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganLingkunganKerja extends Sector
{
    protected $table = "data_lapangan_lingkungan_kerja";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function detailLingkunganKerja(){
        return $this->hasMany(DetailLingkunganKerja::class, 'no_sampel', 'no_sampel');
    }
}