<?php

namespace App\Models;

class MobilisasiOperasional extends Sector
{
    protected $table = 'mobilisasi_operasional';
    public $timestamps = false;
    protected $guarded = [];

    public function details()
    {
        return $this->hasMany(MobilisasiOperasionalDetail::class, 'id_mo')
            ->where('is_active', true);
    }

    public function mobil()
    {
        return $this->belongsTo(DaftarMobil::class, 'id_mobil');
    }

    public function driver()
    {
        return $this->belongsTo(MasterDriver::class, 'id_driver', 'user_id');
    }
}
