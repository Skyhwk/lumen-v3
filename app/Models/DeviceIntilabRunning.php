<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DeviceIntilabRunning extends Sector {
    protected $table = "device_intilab_running";
    public $timestamps = false;

    protected $guarded = [];

    protected $appends = ['is_running', 'kodeAlat', 'status', 'nama'];

    protected $hidden  = ['device'];

    public function getIsRunningAttribute() {
        return $this->is_active ? true : false;
    }

    public function device() {
        return $this->belongsTo(DeviceIntilab::class, 'device_id');
    }

    public function alat() {
        return $this->belongsTo(DeviceIntilab::class, 'device_id');
    }

    public function getKodeAlatAttribute() {
        return $this->device->kode;
    }

    public function getStatusAttribute() {
        return $this->device->status;
    }

    public function getNamaAttribute() {
        return $this->device->nama;
    }

    public function offlineReason(){
        return $this->hasMany(DeviceOfflineReason::class, 'device_running_id', 'id');
    }
}