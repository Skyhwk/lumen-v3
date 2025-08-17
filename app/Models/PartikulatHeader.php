<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PartikulatHeader extends Sector
{
    protected $table = 'partikulat_header';
    public $timestamps = false;
    protected $guarded = [];

    public function data_lapangan()
    {
        return $this->hasMany(DataLapanganPartikulatMeter::class, 'no_sampel', 'no_sampel');
    }
    public function ws_udara()
    {
        return $this->belongsTo(WsValueUdara::class, 'id', 'id_partikulat_header');
    }
}