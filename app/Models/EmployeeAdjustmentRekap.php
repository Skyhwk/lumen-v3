<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdjustmentRekap extends Model
{
    protected $table = 'employee_adjustment_rekap';

    protected $guarded = [];

    protected $casts = [
        'payload_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(SalaryAdjustmentRequest::class, 'request_id');
    }
}
