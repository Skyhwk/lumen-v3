<?php

namespace App\Models;

use App\Models\Sector;

class CatatanKebisinganSoundMeter extends Sector
{
    protected $table = 'catatan_kebisingan_sound_meter';
    public $timestamps = false;
    protected $guarded = [];
}