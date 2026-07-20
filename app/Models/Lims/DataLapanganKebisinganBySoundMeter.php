<?php

namespace App\Models\Lims;

use App\Models\CatatanKebisinganSoundMeter;

use App\Models\Sector;

class DataLapanganKebisinganBySoundMeter extends Sector
{
    protected $connection = 'lims';

    protected $table = "data_lapangan_kebisingan_by_sound_meter";
    public $timestamps = false;
    protected $guarded = [];

    public function detail()
    {
        return $this->belongsTo('App\Models\Lims\OrderDetail', 'no_sampel', 'no_sampel')
            ->where('is_active', true);
    }

    public function catatan()
    {
        return $this->hasMany(CatatanKebisinganSoundMeter::class, 'id_kebisingan', 'id');
    }

    public function kebisinganHeader()
    {
        return $this->hasOne(KebisinganHeader::class, 'no_sampel', 'no_sampel')
            ->where('is_active', true)
            ->orderBy('id', 'desc');
    }
}