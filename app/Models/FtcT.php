<?php

namespace App\Models;

use App\Models\Sector;

class FtcT extends Sector
{
    protected $connection = 'mysql';
    public $timestamps = false;
    protected $guarded = [];

    public function getTable()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $mainDb . '.t_ftc_t';
    }
}
