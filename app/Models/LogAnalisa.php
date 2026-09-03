<?php

namespace App\Models;

class LogAnalisa extends Sector
{
    protected $table = 'log_analisa';
    protected $guarded = [];

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active', 1);
    }
}
