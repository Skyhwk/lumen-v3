<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DeviceIntilab;

class DataLapanganUnion extends Sector
{
    protected $connection = 'mysql';
    public $timestamps = false;
    protected $guarded = [];

    public function getTable()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $mainDb . '.data_lapangan_union';
    }
}