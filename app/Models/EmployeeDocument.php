<?php

namespace App\Models;

class EmployeeDocument extends Sector
{
    protected $table = 'employee_documents';
    protected $guarded = ['id'];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
