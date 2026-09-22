<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentRequest extends Model
{
    protected $table = 'salary_adjustment_requests';

    protected $guarded = [];

    public function kpi()
    {
        return $this->hasOne(SalaryAdjustmentKpi::class, 'request_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(SalaryAdjustmentStatusLog::class, 'request_id');
    }

    public function assessment()
    {
        return $this->hasOne(SalaryAdjustmentAssessment::class, 'request_id');
    }

    public function counseling()
    {
        return $this->hasOne(SalaryAdjustmentCounseling::class, 'request_id');
    }
}
