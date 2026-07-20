<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterFeeDriver extends Sector
{
    protected $connection = 'lims';
    protected $table = 'master_fee_driver';

    protected $guarded = [];

    public $timestamps = false; 

    public function driver()
    {
        return $this->belongsTo(MasterDriver::class, 'driver_id', 'user_id');
    }
}
