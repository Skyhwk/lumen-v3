<?php

namespace App\Models;

use App\Models\Sector;

class PelangganBlacklist extends Sector
{
    protected $table = 'pelanggan_blacklist';
    protected $guarded = ['id'];

    public $timestamps = false;
}
