<?php

namespace App\Models;

use App\Models\Sector;

class TcOrderDetail extends Sector
{
    protected $connection = 'mysql';
    public $timestamps = false;

    public function getTable()
    {
        $mainDb = \DB::connection('mysql')->getDatabaseName();
        return $mainDb . '.tc_order_detail';
    }

    public function orderDetail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'id_order_detail', 'id');
    }
}
