<?php

namespace App\Models;

use App\Models\Sector;

class PenggunaanMobilHeader extends Sector
{
    protected $table = 'penggunaan_mobil_header';
    public $timestamps = false;
    protected $guarded = [];

    public function requester()
    {
        return $this->belongsTo(MasterKaryawan::class, 'requester_id');
    }

    public function details()
    {
        return $this->hasMany(PenggunaanMobilDetail::class, 'header_id');
    }

    public function drivers()
    {
        return $this->hasMany(PenggunaanMobilDriver::class, 'header_id');
    }
}