<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrAttendanceCorrectionDetail extends Model
{
    protected $table = 'hr_attendance_correction_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    public function request()
    {
        return $this->belongsTo(HrRequest::class, 'request_id');
    }
}
