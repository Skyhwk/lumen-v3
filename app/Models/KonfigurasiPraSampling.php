<?php

namespace App\Models;

use App\Models\Sector;

class KonfigurasiPraSampling extends Sector
{
    protected $table = "konfigurasi_pra_sampling";
    public $timestamps = false;

    protected $guarded = [];

    public function kategori()
    {
        return $this->belongsTo(MasterKategori::class, 'id_kategori', 'id');
    }
}
