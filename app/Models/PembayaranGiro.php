<?php

namespace App\Models;

use App\Models\Sector;

class PembayaranGiro extends Sector
{
    protected $table = 'pembayaran_giro';
    public $timestamps = false;

    protected $guarded = [];

}
