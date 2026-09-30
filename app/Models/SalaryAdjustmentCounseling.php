<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentCounseling extends Model
{
    protected $table = 'salary_adjustment_counselings';

    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(SalaryAdjustmentRequest::class, 'request_id');
    }
}
