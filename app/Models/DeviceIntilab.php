<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DeviceIntilab extends Sector {
    protected $table = "device_intilab";
    public $timestamps = false;

    protected $guarded = [];
}