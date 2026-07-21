<?php

namespace App\Models;

use App\Models\Sector;

class DataLapanganUnion extends Sector
{
    protected $connection = 'mysql';
    public static $useLimsDetail = false;
    public $timestamps = false;
    protected $guarded = [];

    public function getTable()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $mainDb . '.data_lapangan_union';
    }
}