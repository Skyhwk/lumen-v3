<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentKpiCriteria extends Model
{
    protected $table = 'salary_adjustment_kpi_criteria';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
