<?php

namespace App\Models;

use App\Models\Sector;

class MasterWilayahSampling extends Sector
{
    protected $table = 'master_wilayah_sampling';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }
}
