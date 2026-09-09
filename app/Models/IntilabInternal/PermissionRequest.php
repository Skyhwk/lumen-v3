<?php

namespace App\Models\IntilabInternal;

use Illuminate\Database\Eloquent\Model;

class PermissionRequest extends Model
{
    protected $connection = 'intilab_apps';
    protected $table = 'permission_requests';
    public $timestamps = false;
    protected $guarded = [];

    public function karyawan()
    {
        return $this->belongsTo(\App\Models\MasterKaryawan::class, 'employee_id', 'user_id');
    }
}
