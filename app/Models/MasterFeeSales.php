<?php

namespace App\Models;

use App\Models\Sector;

class MasterFeeSales extends Sector
{
    protected $guarded = ['id'];
    public $timestamps = false;

    public function rekap()
    {
        return $this->hasMany(RekapFeeSales::class, 'fee_sales_id');
    }
}
