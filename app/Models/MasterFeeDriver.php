<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterFeeDriver extends Sector
{
    protected $table = 'master_fee_driver';

    protected $guarded = [];

    public $timestamps = false; 

    public function driver()
    {
        return $this->belongsTo(MasterDriver::class, 'driver_id', 'id');
    }
}
