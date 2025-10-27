<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganLingkunganHidup extends Sector
{
    protected $table = "data_lapangan_lingkungan_hidup";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function detailLingkunganHidup(){
        return $this->hasMany(DetailLingkunganHidup::class, 'no_sampel', 'no_sampel');
    }
}