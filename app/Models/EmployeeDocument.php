<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    protected $table = 'employee_documents';

    protected $guarded = ['id'];

    protected $casts = [
        'sequence_year' => 'integer',
        'sequence_number' => 'integer',
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }

    public function recruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
