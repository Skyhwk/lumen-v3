<?php

namespace App\Services\Hr\Presenters;

use App\Models\Hr\HrRequest;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;

class OvertimeRequestPresenter
{
    public static function toGreatdayJson(HrRequest $request): object
    {
        $detail = $request->overtimeDetail;
        $divisi = MasterDivisi::find($request->id_department);

        $members = $request->overtimeParticipants->map(function ($p) {
            $k = MasterKaryawan::find($p->karyawan_id);

            return (object) [
                'id' => $p->id,
                'overtime_request_id' => $p->request_id,
                'employee_id' => $p->karyawan_id,
                'employee_name' => $k->nama_lengkap ?? '',
                'is_active' => $p->is_active,
            ];
        });

        return (object) array_merge([
            'id' => $request->id,
            'no_document' => $request->no_document,
            'department_id' => $request->id_department,
            'department_name' => $divisi->nama_divisi ?? '',
            'start_date' => $detail->start_date ?? null,
            'end_date' => $detail->end_date ?? null,
            'start_time' => $detail->start_time ?? null,
            'end_time' => $detail->end_time ?? null,
            'description' => $request->description,
            'status' => $request->status,
            'created_at' => $request->created_at,
            'created_by' => $request->created_by_name,
            'atasan_approval_steps' => HrApprovalChainPresenter::atasanStepsForGreatday($request),
            'members' => $members,
        ], HrApprovalChainPresenter::workflowRejectionFields($request));
    }
}
