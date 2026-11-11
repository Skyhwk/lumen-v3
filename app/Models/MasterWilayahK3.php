<?php

namespace App\Models;

class MasterWilayahK3 extends Sector
{
    protected $table = 'master_wilayah_k3';

    protected $fillable = [
        'wilayah_pelanggan',
        'id_cabang',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public $timestamps = false;

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }

    public function wilayahSampling()
    {
        return $this->belongsToMany(
            MasterWilayahSampling::class,
            'master_wilayah_k3_sampling',
            'master_wilayah_k3_id',
            'id_master_wilayah_sampling'
        );
    }
}
