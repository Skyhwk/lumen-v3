<?php

namespace App\Models;

class KategoriPeralatanSampling extends Sector
{
    protected $table = 'kategori_peralatan_sampling';

    protected $guarded = ['id'];

    protected $casts = [
        'id_cabang' => 'integer',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }
}
