<?php

namespace App\Models\Lims;

use App\Models\Sector;

class TcOrderDetail extends Sector
{
    protected $connection = 'lims';

    protected $table = "tc_order_detail";
    public $timestamps = false;

    public function orderDetail()
    {
        return $this->belongsTo('App\Models\Lims\OrderDetail', 'id_order_detail', 'id');
    }
}
