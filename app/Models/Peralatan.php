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
<<<<<<< HEAD
=======

    public function monitoring()
    {
        return $this->hasMany(MonitoringPeralatan::class, 'id_peralatan');
    }
>>>>>>> f6302001cb585b5c181d098be33c3683a801e845
}
