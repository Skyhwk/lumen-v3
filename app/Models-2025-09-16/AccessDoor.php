<?php

namespace App\Models;

use App\Models\Sector;

class AccessDoor extends Sector
{
    protected $table = "access_door";
    protected $guarded = ["id"];
    public $timestamps = false;

    public function device()
    {
        return $this->belongsTo(Devices::class, 'kode_mesin', 'kode_device');
    }

    public function rfid()
    {
        return $this->belongsTo(RfidCard::class, 'kode_rfid', 'kode_kartu');
    }
}
