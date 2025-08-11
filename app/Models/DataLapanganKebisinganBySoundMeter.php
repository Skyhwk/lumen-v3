<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DataLapanganKebisinganBySoundMeter extends Sector
{
    protected $table = "data_lapangan_kebisingan_by_sound_meter";
    public $timestamps = false;
    protected $guarded = [];

    public function detail(){
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel')
        ->where('is_active', true);
    }

    public function catatan(){
        return $this->hasMany(CatatanKebisinganSoundMeter::class, 'id_kebisingan', 'id');
    }
    
}