<?php

namespace App\Models\IntilabInternal;

use Illuminate\Database\Eloquent\Model;

class ConsultationRequest extends Model
{
    protected $connection = 'intilab_apps';
    protected $table = 'consultation_requests';
    public $timestamps = false;
    protected $guarded = [];

    public function karyawan()
    {
        return $this->belongsTo(\App\Models\MasterKaryawan::class, 'employee_id', 'id');
    }
}
