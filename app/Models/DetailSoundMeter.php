<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\DeviceIntilab;
use App\Models\Sector;

class DetailSoundMeter extends Sector
{
    protected $table = 'detail_sound_meter';
    public $timestamps = false;

    protected $guarded = [];

    public function device()
    {
        return $this->belongsTo(DeviceIntilab::class, 'id_device', 'kode');
    }
}