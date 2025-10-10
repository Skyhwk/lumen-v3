<?php

namespace App\Models;

use App\Models\Sector;

class PermintaanDokumentasiSampling extends Sector
{
    protected $table = 'permintaan_dokumentasi_sampling';
    protected $guarded = ['id'];

    public $timestamps = false;
}
