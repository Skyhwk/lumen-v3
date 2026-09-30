<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdjustmentApplyLog extends Model
{
    protected $table = 'employee_adjustment_apply_logs';

    protected $guarded = [];

    protected $casts = [
        'is_success' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(SalaryAdjustmentRequest::class, 'request_id');
    }
}
