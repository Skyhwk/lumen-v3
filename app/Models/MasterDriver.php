<?php

namespace App\Models;

use App\Models\Sector;

class MasterDriver extends Sector
{
    protected $table = "master_driver";
    public $timestamps = false;
    protected $guarded = [];

    public function fee()
    {
        return $this->hasOne(MasterFeeDriver::class, 'driver_id', 'id')->where('is_active', true);
    }

    public function Allfee()
    {
        return $this->hasMany(MasterFeeDriver::class, 'driver_id', 'id')->orderBy('created_at', 'desc');
    }

}