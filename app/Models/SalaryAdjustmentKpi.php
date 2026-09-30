<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentKpi extends Model
{
    protected $table = 'salary_adjustment_kpi';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(SalaryAdjustmentKpiItem::class, 'kpi_id');
    }
}
