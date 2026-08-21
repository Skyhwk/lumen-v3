<?php
namespace App\Models\IntilabInternal;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrections extends Model {
    
    protected $connection = 'intilab_apps';
    protected $table   = "attendance_corrections";
    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(\App\Models\MasterKaryawan::class, 'employee_id', 'id');
    }

    protected $guarded = [];
}