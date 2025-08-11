<?php

namespace App\Models;

use App\Models\Sector;

class PraOrderDetail extends Sector
{
    protected $table = 'pra_order_detail';
    public $timestamps = false;
    protected $guarded = [];
}