<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrOvertimeDetail extends Model
{
    protected $table = 'hr_overtime_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
