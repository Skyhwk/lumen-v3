<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DeviceIntilab;

class DataLapanganUnion extends Sector
{
    protected $table = 'data_lapangan_union';
    public $timestamps = false;

    protected $guarded = [];
}