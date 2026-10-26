<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrSpecialLeaveType extends Model
{
    protected $table = 'hr_special_leave_type';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
