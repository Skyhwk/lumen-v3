<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\DeviceIntilab;
use App\Models\Sector;

class DetailFlowMeter extends Sector
{
    protected $table = 'detail_flow_meter';
    public $timestamps = false;

    protected $guarded = [];

    public function device()
    {
        return $this->belongsTo(DeviceIntilab::class, 'id_device', 'kode');
    }
}