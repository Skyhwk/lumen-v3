<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DeviceIntilab;

class DataLapanganUnion extends Sector
{
    protected $connection = 'lims';

    protected $table = 'data_lapangan_union';
    public $timestamps = false;

    protected $guarded = [];
}