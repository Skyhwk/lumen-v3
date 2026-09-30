<?php

namespace App\Services\Hr\Presenters;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Support\Greatday\GreatdayAssetPaths;

class AttendanceCorrectionPresenter
{
    public static function toGreatdayJson(HrRequest $request): object
    {
        $detail = $request->attendanceCorrectionDetail;
        $karyawan = MasterKaryawan::find($request->karyawan_id);

        $stored = $detail->attachment_path ?? null;
        $attachments = HrFormAttachmentStorage::resolvePublicUrls($stored, GreatdayAssetPaths::KEY_KOREKSI_ABSEN);
        $attachment = $attachments[0] ?? null;

        return (object) array_merge([
            'id' => $request->id,
            'employee_id' => $request->karyawan_id,
            'type' => $detail->correction_type ?? null,
            'date' => $detail->correction_date ?? null,
            'time' => $detail->correction_time ?? null,
            'description' => $request->description,
            'attachment' => $attachment,
            'attachments' => $attachments,
            'status' => $request->status,
            'created_at' => $request->created_at,
            'created_by' => $request->created_by_name,
            'atasan_approval_steps' => HrApprovalChainPresenter::atasanStepsForGreatday($request),
            'employee_name' => $karyawan->nama_lengkap ?? '',
            'employee_position' => $karyawan->jabatan ?? '',
        ], HrApprovalChainPresenter::workflowRejectionFields($request));
    }
}
