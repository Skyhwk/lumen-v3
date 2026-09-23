<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentAssessmentSession extends Model
{
    protected $table = 'salary_adjustment_assessment_sessions';

    protected $guarded = [];

    protected $casts = [
        'questions_json' => 'array',
        'answers_json' => 'array',
        'result_json' => 'array',
    ];
}
