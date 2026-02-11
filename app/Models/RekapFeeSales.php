<?php

namespace App\Models;

use App\Models\Sector;

class RekapFeeSales extends Sector
{
    protected $guarded = ['id'];
    public $timestamps = false;

    public function masterFeeSales()
    {
        return $this->belongsTo(MasterFeeSales::class, 'fee_sales_id');
    }
}
