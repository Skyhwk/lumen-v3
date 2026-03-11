<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DeviceOfflineReason extends Sector {
    protected $table = "device_offline_reason";
    public $timestamps = false;

    protected $guarded = [];
}