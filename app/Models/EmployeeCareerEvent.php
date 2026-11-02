<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCareerEvent extends Model
{
    public const TYPE_JABATAN = 'jabatan_change';
    public const TYPE_STATUS = 'status_karyawan_change';
    public const TYPE_KONTRAK = 'kontrak_end_change';
    public const TYPE_SALARY = 'salary_change';

    protected $table = 'employee_career_events';

    protected $guarded = [];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'effective_at' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(MasterKaryawan::class, 'employee_id');
    }

    public function request()
    {
        return $this->belongsTo(SalaryAdjustmentRequest::class, 'request_id');
    }
}
