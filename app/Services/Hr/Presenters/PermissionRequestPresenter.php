<?php

namespace App\Services\Hr\Presenters;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;

class PermissionRequestPresenter
{
    public static function toGreatdayJson(HrRequest $request): object
    {
        $detail = $request->permissionDetail;
        $karyawan = MasterKaryawan::find($request->karyawan_id);

        $type = 'Event Leave';
        if ($detail) {
            if ($detail->permission_kind === 'sick') {
                $type = 'Sick Leave';
            } elseif ($detail->permission_kind === 'late') {
                $type = 'Late Arrival';
            }
        }

        $attachment = $detail->attachment_path ?? null;
        if ($attachment) {
            $attachment = url('permission-requests/' . $attachment);
        }

        return (object) [
            'id' => $request->id,
            'employee_id' => $request->karyawan_id,
            'no_document' => $request->no_document,
            'type' => $type,
            'start_date' => $detail->start_date ?? null,
            'end_date' => $detail->end_date ?? null,
            'start_time' => $detail->start_time ?? null,
            'end_time' => $detail->end_time ?? null,
            'description' => $request->description,
            'attachment' => $attachment,
            'status' => $request->status,
            'created_by' => $request->created_by_name,
            'employee_name' => $karyawan->nama_lengkap ?? '',
            'employee_position' => $karyawan->jabatan ?? '',
        ];
    }
}
