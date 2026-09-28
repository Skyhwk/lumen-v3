<?php

namespace App\Services\Hr\Presenters;

use App\Models\Hr\HrRequest;
use App\Models\Hr\HrSpecialLeaveType;
use App\Models\MasterKaryawan;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Support\Greatday\GreatdayAssetPaths;

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
            } elseif ($detail->leave_kind === 'phl') {
                $type = 'Holiday Replacement Leave';
            }
        }

        $special = null;
        if ($detail && $detail->special_leave_type_id) {
            $special = HrSpecialLeaveType::find($detail->special_leave_type_id);
        }

        $stored = $detail->attachment_path ?? null;
        $attachments = HrFormAttachmentStorage::resolvePublicUrls($stored, GreatdayAssetPaths::KEY_CUTI);
        $attachment = $attachments[0] ?? null;

        return (object) array_merge([
            'id' => $request->id,
            'employee_id' => $request->karyawan_id,
            'no_document' => $request->no_document,
            'type' => $type,
            'special_leave_id' => $detail->special_leave_type_id ?? null,
            'start_date' => $detail->start_date ?? null,
            'end_date' => $detail->end_date ?? null,
            'description' => $request->description,
            'attachment' => $attachment,
            'attachments' => $attachments,
            'status' => $request->status,
            'created_at' => $request->created_at,
            'created_by' => $request->created_by_name,
            'atasan_approval_steps' => HrApprovalChainPresenter::atasanStepsForGreatday($request),
            'employee_name' => $karyawan->nama_lengkap ?? '',
            'employee_position' => $karyawan->jabatan ?? '',
            'specialLeaveType' => $special,
        ], HrApprovalChainPresenter::workflowRejectionFields($request));
    }
}
