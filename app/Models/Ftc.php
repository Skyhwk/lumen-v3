<?php

namespace App\Models;

use App\Models\Sector;

class Ftc extends Sector
{
    protected $connection = 'mysql';
    public $timestamps = false;
    protected $guarded = [];

    public function getTable()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $mainDb . '.t_ftc';
    }

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sample', 'no_sampel');
    }
}
