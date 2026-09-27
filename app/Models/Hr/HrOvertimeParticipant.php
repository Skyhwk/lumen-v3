<?php

namespace App\Models\Hr;

use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Model;

class HrOvertimeParticipant extends Model
{
    protected $table = 'hr_overtime_participant';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }
}
