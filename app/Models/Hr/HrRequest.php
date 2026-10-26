<?php

namespace App\Models\Hr;

use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Model;

class HrRequest extends Model
{
    public const TYPE_LEAVE = 'leave';

    public const TYPE_PERMISSION = 'permission';

    public const TYPE_OVERTIME = 'overtime';

    public const TYPE_ATTENDANCE_CORRECTION = 'attendance_correction';

    public const TYPE_CONSULTATION = 'consultation';

    public const TYPE_EVENT_REPORT = 'event_report';

    protected $table = 'hr_request';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id');
    }

    public function approvalSteps()
    {
        return $this->hasMany(HrApprovalStep::class, 'request_id');
    }

    public function leaveDetail()
    {
        return $this->hasOne(HrLeaveDetail::class, 'request_id');
    }

    public function permissionDetail()
    {
        return $this->hasOne(HrPermissionDetail::class, 'request_id');
    }

    public function overtimeDetail()
    {
        return $this->hasOne(HrOvertimeDetail::class, 'request_id');
    }

    public function overtimeParticipants()
    {
        return $this->hasMany(HrOvertimeParticipant::class, 'request_id')
            ->where('is_active', true);
    }

    public function attendanceCorrectionDetail()
    {
        return $this->hasOne(HrAttendanceCorrectionDetail::class, 'request_id');
    }

    public function consultationDetail()
    {
        return $this->hasOne(HrConsultationDetail::class, 'request_id');
    }

    public function eventReportDetail()
    {
        return $this->hasOne(HrEventReportDetail::class, 'request_id');
    }
}
