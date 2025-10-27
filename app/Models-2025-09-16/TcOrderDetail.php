<?php

namespace App\Models;

use App\Models\Sector;

class TcOrderDetail extends Sector
{
    protected $table = "tc_order_detail";
    public $timestamps = false;

    public function orderDetail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'id_order_detail', 'id');
    }
}
