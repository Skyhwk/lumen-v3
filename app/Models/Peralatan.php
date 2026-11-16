<?php

namespace App\Models;

class Peralatan extends Sector
{
    protected $table = 'peralatan';

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
