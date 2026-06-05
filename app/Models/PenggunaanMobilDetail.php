<?php

namespace App\Models;

use App\Models\Sector;

class PenggunaanMobilDetail extends Sector
{
    protected $table = 'penggunaan_mobil_detail';
    public $timestamps = false;
    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(PenggunaanMobilHeader::class, 'header_id');
    }
}
