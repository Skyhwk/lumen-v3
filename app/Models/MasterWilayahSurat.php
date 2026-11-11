<?php

namespace App\Models;

class MasterWilayahSurat extends Sector
{
    protected $table = 'master_wilayah_surat';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function sampling()
    {
        return $this->belongsToMany(
            MasterWilayahSampling::class,
            'master_wilayah_surat_sampling',
            'master_wilayah_surat_id',
            'id_master_wilayah_sampling'
        );
    }
}
