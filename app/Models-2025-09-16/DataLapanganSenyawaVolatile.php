<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganSenyawaVolatile extends Sector
{
    protected $table = "data_lapangan_senyawa_volatile";
    public $timestamps = false;

    protected $guarded = [];

    public function orderDetail(){
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function detailSenyawaVolatile(){
        return $this->hasMany(DetailSenyawaVolatile::class, 'no_sampel', 'no_sampel');
    }
}