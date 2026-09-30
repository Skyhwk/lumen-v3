<?php
namespace App\Models\Greatday;

class LeaveRequest extends GreatdayAppModel
{
    protected $table = 'leave_requests';

    protected $guarded = ['id'];

    public function specialLeaveType()
    {
        return $this->belongsTo(SpecialLeaveType::class, 'special_leave_id');
    }
}
