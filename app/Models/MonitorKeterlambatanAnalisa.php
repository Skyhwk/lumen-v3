<?php

namespace App\Models;

class MonitorKeterlambatanAnalisa extends Sector
{
    protected $table = 'monitor_keterlambatan_analisa';
    protected $guarded = [];

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel');
    }
}
