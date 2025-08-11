<?php

namespace App\Models;

use App\Models\Sector;

class Ftc extends Sector
{
    protected $table = 't_ftc';
    public $timestamps = false;
    protected $guarded = [];

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sample', 'no_sampel');
    }
}
