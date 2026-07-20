<?php

namespace App\Models\Lims;

use App\Models\MasterKategori;

use App\Models\Sector;

class KonfigurasiPraSampling extends Sector
{
    protected $connection = 'lims';

    protected $table = "konfigurasi_pra_sampling";
    public $timestamps = false;

    protected $guarded = [];

    public function kategori()
    {
        return $this->belongsTo(MasterKategori::class, 'id_kategori', 'id');
    }
}
