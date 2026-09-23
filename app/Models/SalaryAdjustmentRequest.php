<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdjustmentRequest extends Model
{
    protected $table = 'salary_adjustment_requests';

    protected $guarded = [];

    protected $casts = [
        'has_salary_adjustment' => 'boolean',
        'type_metadata' => 'array',
        'tanggal_efektif' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'tanggal_berakhir_kerja' => 'date',
        'scheduled_apply_at' => 'date',
        'receiver_responded_at' => 'datetime',
    ];

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

    public function rekap()
    {
        return $this->hasOne(EmployeeAdjustmentRekap::class, 'request_id');
    }

    public function applyLogs()
    {
        return $this->hasMany(EmployeeAdjustmentApplyLog::class, 'request_id');
    }

    public function newJabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'new_jabatan_id');
    }

    public function receiverManager()
    {
        return $this->belongsTo(MasterKaryawan::class, 'receiver_manager_id');
    }
}
