<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentAssessment extends Model
{
    protected $table = 'salary_adjustment_assessments';

    protected $guarded = [];

    protected $casts = [
        'result_json' => 'array',
        'is_link_active' => 'boolean',
        'has_time_limit' => 'boolean',
    ];

    public function sessions()
    {
        return $this->hasMany(SalaryAdjustmentAssessmentSession::class, 'assessment_id');
    }

    public function request()
    {
        return $this->belongsTo(SalaryAdjustmentRequest::class, 'request_id');
    }
}
