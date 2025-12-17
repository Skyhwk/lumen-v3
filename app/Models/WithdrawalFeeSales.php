<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class WithdrawalFeeSales extends Sector
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'sales_id');
    }
}
