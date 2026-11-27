<?php

namespace App\Models;

class PenggunaanMobilDriver extends Sector
{
    protected $table = 'penggunaan_mobil_driver';
    public $timestamps = false;
    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(PenggunaanMobilHeader::class, 'header_id');
    }

    public function driver()
    {
        return $this->belongsTo(MasterDriver::class, 'driver_id', 'user_id');
    }
}