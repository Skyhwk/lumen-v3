<?php

namespace App\Models\Lims;

use App\Models\Sector;

class Ftc extends Sector
{
    protected $connection = 'lims';

    protected $table = 't_ftc';
    public $timestamps = false;
    protected $guarded = [];

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sample', 'no_sampel');
    }
}
