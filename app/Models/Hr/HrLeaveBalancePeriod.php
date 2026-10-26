<?php

namespace App\Models\Hr;

use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Model;

class HrLeaveBalancePeriod extends Model
{
    protected $table = 'hr_leave_balance_period';

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_imported_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
