<?php

namespace App\Models\IntilabInternal;

use Illuminate\Database\Eloquent\Model;

class OvertimeReimbursement extends Model
{
    protected $connection = 'intilab_apps';
    protected $table = 'overtime_reimbursements';
    public $timestamps = false;
    protected $guarded = [];

    public function karyawan()
    {
        return $this->belongsTo(\App\Models\MasterKaryawan::class, 'employee_id', 'id');
    }
}
