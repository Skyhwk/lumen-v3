<?php

namespace App\Models;

use App\Models\Sector;

class KonfirmasiLhp extends Sector
{
    protected $table = "konfirmasi_lhp";
    protected $guarded = ['id'];

    public $timestamps = false;
}
