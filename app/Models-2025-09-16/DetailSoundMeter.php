<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DeviceIntilab;

class DetailSoundMeter extends Sector
{
    protected $table = 'detail_sound_meter';
    public $timestamps = false;

    protected $guarded = [];
    
    // relasi ke model DeviceIntilab
    public function device()
    {
        return $this->belongsTo(DeviceIntilab::class, 'id_device', 'kode');
    }
}