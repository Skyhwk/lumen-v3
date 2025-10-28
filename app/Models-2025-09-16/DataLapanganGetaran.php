<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganGetaran extends Sector
{
    protected $table = "data_lapangan_getaran";
    public $timestamps = false;

    protected $guarded = [];

    public function detail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function sub_kategori(){
        return $this->belongsTo(MasterSubKategori::class, 'kategori_3', 'id')
        ->where('is_active', true);
    }
}