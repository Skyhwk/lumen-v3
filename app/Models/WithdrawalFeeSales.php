<?php

namespace App\Models;

use App\Models\Sector;

class WithdrawalFeeSales extends Sector
{
    protected $guarded = ['id'];
    public $timestamp = false;

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id');
    }
}
