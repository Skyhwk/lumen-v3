<?php

namespace App\Models;

use App\Models\Sector;

class MutasiAsetPerusahaan extends Sector
{
    protected $table = "mutasi_aset_perusahaan";
    protected $guarded = ['id'];

    public $timestamps = false;

    public function aset()
    {
        return $this->belongsTo(MasterAset::class, 'id_aset_perusahaan', 'id');
    }
}
