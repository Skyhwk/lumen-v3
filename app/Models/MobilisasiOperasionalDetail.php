<?php

namespace App\Models;

class MobilisasiOperasionalDetail extends Sector
{
    protected $table = 'mobilisasi_operasional_detail';
    public $timestamps = false;
    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(MobilisasiOperasional::class, 'id_mo');
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal');
    }
}
