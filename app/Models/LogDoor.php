<?php
namespace App\Models;

use App\Models\Sector;

class LogDoor extends Sector
{
    protected $table = "log_door";
    public $timestamps = false;
    protected $guarded = [];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'userid');
    }

    public function device()
    {
        return $this->belongsTo(Devices::class, 'kode_mesin', 'kode_device');
    }
}