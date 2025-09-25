<?php

namespace App\Models;

use App\Models\Sector;

use Illuminate\Database\Eloquent\SoftDeletes;

class TargetSales extends Sector
{
    use SoftDeletes;

    protected $table = 'target_sales';
    protected $guarded = ['id'];

    public function sales()
    {
        return $this->belongsTo(MasterKaryawan::class, 'user_id');
    }
}
