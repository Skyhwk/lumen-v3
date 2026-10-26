<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrLeaveDetail extends Model
{
    protected $table = 'hr_leave_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
