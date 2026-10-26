<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrLeaveBalanceLedger extends Model
{
    public const TYPE_ALPA = 'alpa';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const SOURCE_ATTENDANCE = 'attendance_sync';

    public const SOURCE_HRD = 'hrd_portal';

    protected $table = 'hr_leave_balance_ledger';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'is_void' => 'boolean',
        'reference_date' => 'date',
        'created_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(HrLeaveBalancePeriod::class, 'balance_period_id');
    }
}
