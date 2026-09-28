<?php

namespace App\Services\Hr\Presenters;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;

class AttendanceCorrectionPresenter
{
    public static function toGreatdayJson(HrRequest $request): object
    {
        $detail = $request->attendanceCorrectionDetail;
        $karyawan = MasterKaryawan::find($request->karyawan_id);

        $attachment = $detail->attachment_path ?? null;
        if ($attachment) {
            $attachment = url('attendance-corrections/' . $attachment);
        }

        return (object) [
            'id' => $request->id,
            'employee_id' => $request->karyawan_id,
            'type' => $detail->correction_type ?? null,
            'date' => $detail->correction_date ?? null,
            'time' => $detail->correction_time ?? null,
            'description' => $request->description,
            'attachment' => $attachment,
            'status' => $request->status,
            'created_by' => $request->created_by_name,
            'employee_name' => $karyawan->nama_lengkap ?? '',
            'employee_position' => $karyawan->jabatan ?? '',
        ];
    }
}
