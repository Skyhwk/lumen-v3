<?php

namespace App\Models;

use App\Models\Sector;

class PenggunaanMobilHeader extends Sector
{
    protected $table = 'penggunaan_mobil_header';
    public $timestamps = false;
    protected $guarded = [];

    public function mobil()
    {
        return $this->belongsTo(DaftarMobil::class, 'mobil_id');
    }

    public function driver()
    {
        return $this->belongsTo(MasterDriver::class, 'driver_id', 'user_id');
    }

    public function requester()
    {
        return $this->belongsTo(MasterKaryawan::class, 'requester_id');
    }

    public function details()
    {
        return $this->hasMany(PenggunaanMobilDetail::class, 'header_id');
    }
}
