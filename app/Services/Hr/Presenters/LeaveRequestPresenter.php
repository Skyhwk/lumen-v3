<?php

namespace App\Services\Hr\Presenters;

use App\Models\Hr\HrRequest;
use App\Models\Hr\HrSpecialLeaveType;
use App\Models\MasterKaryawan;

class LeaveRequestPresenter
{
    public static function toGreatdayJson(HrRequest $request): object
    {
        $detail = $request->leaveDetail;
        $karyawan = MasterKaryawan::find($request->karyawan_id);

        $type = 'Annual Leave';
        if ($detail) {
            if ($detail->leave_kind === 'special') {
                $type = 'Special Leave';
            } elseif ($detail->leave_kind === 'unpaid') {
                $type = 'Unpaid Leave';
            }
        }

        $special = null;
        if ($detail && $detail->special_leave_type_id) {
            $special = HrSpecialLeaveType::find($detail->special_leave_type_id);
        }

        $attachment = $detail->attachment_path ?? null;
        if ($attachment) {
            $attachment = url('leave-requests/' . $attachment);
        }

        return (object) [
            'id' => $request->id,
            'employee_id' => $request->karyawan_id,
            'no_document' => $request->no_document,
            'type' => $type,
            'special_leave_id' => $detail->special_leave_type_id ?? null,
            'start_date' => $detail->start_date ?? null,
            'end_date' => $detail->end_date ?? null,
            'description' => $request->description,
            'attachment' => $attachment,
            'status' => $request->status,
            'created_by' => $request->created_by_name,
            'employee_name' => $karyawan->nama_lengkap ?? '',
            'employee_position' => $karyawan->jabatan ?? '',
            'specialLeaveType' => $special,
        ];
    }
}
