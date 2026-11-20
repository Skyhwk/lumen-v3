<?php

namespace App\Models;

class LaporanHarian extends Sector
{
    protected $table = 'laporan_harian';

    protected $guarded = ['id'];

    public $timestamps = false;
}
