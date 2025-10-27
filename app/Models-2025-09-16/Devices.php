<?php

namespace App\Models;

use App\Models\Sector;

class Devices extends Sector
{
    protected $table = "devices";
    protected $guarded = ["id"];
    public $timestamps = false;

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'id_cabang');
    }

    public function accessDoors()
    {
        return $this->hasMany(AccessDoor::class, 'kode_mesin', 'kode_device');
    }

    public function accessLogs()
    {
        return $this->hasMany(\App\Models\LogDoor::class, 'kode_mesin', 'kode_device');
    }
}
