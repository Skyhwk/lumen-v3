<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentApprovalToken extends Model
{
    protected $table = 'salary_adjustment_approval_tokens';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(SalaryAdjustmentRequest::class, 'request_id');
    }
}
