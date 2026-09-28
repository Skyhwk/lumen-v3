<?php

namespace App\Services\Hr;

use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyHrMirror
{
    public function mirrorCreateFromHrRequest(HrRequest $request): void
    {
        if (!HrTableMode::dualWriteLegacy()) {
            return;
        }

        if (HrRequestResolver::legacyIdForHrRequest($request)) {
            return;
        }

        $request->loadMissing([
            'leaveDetail',
            'permissionDetail',
            'overtimeDetail',
            'overtimeParticipants',
            'attendanceCorrectionDetail',
        ]);

        switch ($request->request_type) {
            case HrRequest::TYPE_LEAVE:
                $this->mirrorCreateLeave($request);
                break;
            case HrRequest::TYPE_PERMISSION:
                $this->mirrorCreatePermission($request);
                break;
            case HrRequest::TYPE_OVERTIME:
                $this->mirrorCreateOvertime($request);
                break;
            case HrRequest::TYPE_ATTENDANCE_CORRECTION:
                $this->mirrorCreateAttendanceCorrection($request);
                break;
        }
    }

    public function syncAtasanApproval(HrRequest $request): void
    {
        $legacyId = HrRequestResolver::legacyIdForHrRequest($request);
        $table = HrRequestResolver::legacyTableForType($request->request_type);

        if (!$legacyId || !$table) {
            return;
        }

        $payload = [
            'status' => $request->status,
            'updated_by' => $request->updated_by_name,
            'updated_at' => $request->updated_at,
        ];

        if ($request->status === WorkflowStatus::APPROVED_ATASAN) {
            $payload['approved_atasan_by'] = $request->updated_by_name;
            $payload['approved_atasan_at'] = $request->updated_at;
        } elseif ($request->status === WorkflowStatus::REJECTED_ATASAN) {
            $request->load('approvalSteps');
            $step = $request->approvalSteps->where('step', 'atasan')->sortByDesc('id')->first();
            $payload['rejected_atasan_by'] = $request->updated_by_name;
            $payload['rejected_atasan_at'] = $request->updated_at;
            $payload['reject_atasan_reason'] = $step->reason ?? null;
        }

        DB::connection(config('greatday.legacy_apps_connection'))
            ->table($table)
            ->where('id', $legacyId)
            ->update($payload);
    }

    public function syncVoid(HrRequest $request): void
    {
        $legacyId = HrRequestResolver::legacyIdForHrRequest($request);
        $table = HrRequestResolver::legacyTableForType($request->request_type);

        if (!$legacyId || !$table) {
            return;
        }

        DB::connection(config('greatday.legacy_apps_connection'))
            ->table($table)
            ->where('id', $legacyId)
            ->update([
                'is_active' => $request->is_active ? 1 : 0,
                'updated_by' => $request->updated_by_name,
                'updated_at' => $request->updated_at,
            ]);
    }

    public function syncPortalHrdDecision(HrRequest $request): void
    {
        $legacyId = HrRequestResolver::legacyIdForHrRequest($request);
        $table = HrRequestResolver::legacyTableForType($request->request_type);

        if (!$legacyId || !$table) {
            return;
        }

        $payload = [
            'status' => $request->status,
            'updated_by' => $request->updated_by_name,
            'updated_at' => $request->updated_at,
        ];

        if ($request->status === WorkflowStatus::APPROVED_HRD) {
            $payload['approved_hrd_by'] = $request->updated_by_name;
            $payload['approved_hrd_at'] = $request->updated_at;
        } elseif ($request->status === WorkflowStatus::REJECTED_HRD) {
            $request->load('approvalSteps');
            $step = $request->approvalSteps->where('step', 'hrd')->sortByDesc('id')->first();
            $payload['rejected_hrd_by'] = $request->updated_by_name;
            $payload['rejected_hrd_at'] = $request->updated_at;
            $payload['reject_hrd_reason'] = $step->reason ?? null;
        }

        DB::connection(config('greatday.legacy_apps_connection'))
            ->table($table)
            ->where('id', $legacyId)
            ->update($payload);
    }

    private function mirrorCreateLeave(HrRequest $header): void
    {
        $detail = $header->leaveDetail;
        if (!$detail) {
            return;
        }

        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $legacyId = DB::connection($conn)->table('leave_requests')->insertGetId([
            'employee_id' => $header->karyawan_id,
            'no_document' => $header->no_document,
            'type' => HrLegacyFieldMapper::leaveTypeFromKind($detail->leave_kind),
            'special_leave_id' => HrLegacyFieldMapper::legacySpecialLeaveId($detail->special_leave_type_id),
            'start_date' => $detail->start_date,
            'end_date' => $detail->end_date,
            'description' => $header->description,
            'attachment' => $detail->attachment_path,
            'status' => $header->status,
            'approved_atasan_by' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_by_name : null,
            'approved_atasan_at' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_at : null,
            'created_by' => $header->created_by_name,
            'created_at' => $header->created_at,
            'updated_by' => $header->updated_by_name,
            'updated_at' => $header->updated_at,
            'is_active' => $header->is_active ? 1 : 0,
        ]);

        $this->mapLegacy('leave_requests', $legacyId, $header->id);
    }

    private function mirrorCreatePermission(HrRequest $header): void
    {
        $detail = $header->permissionDetail;
        if (!$detail) {
            return;
        }

        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $legacyId = DB::connection($conn)->table('permission_requests')->insertGetId([
            'employee_id' => $header->karyawan_id,
            'no_document' => $header->no_document,
            'type' => HrLegacyFieldMapper::permissionTypeFromKind($detail->permission_kind),
            'start_date' => $detail->start_date,
            'end_date' => $detail->end_date,
            'start_time' => $detail->start_time,
            'end_time' => $detail->end_time,
            'description' => $header->description,
            'attachment' => $detail->attachment_path ?? '',
            'status' => $header->status,
            'approved_atasan_by' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_by_name : null,
            'approved_atasan_at' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_at : null,
            'created_by' => $header->created_by_name,
            'created_at' => $header->created_at,
            'updated_by' => $header->updated_by_name,
            'updated_at' => $header->updated_at,
            'is_active' => $header->is_active ? 1 : 0,
        ]);

        $this->mapLegacy('permission_requests', $legacyId, $header->id);
    }

    private function mirrorCreateOvertime(HrRequest $header): void
    {
        $detail = $header->overtimeDetail;
        if (!$detail) {
            return;
        }

        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $legacyId = DB::connection($conn)->table('overtime_requests')->insertGetId([
            'no_document' => $header->no_document,
            'department_id' => $header->id_department,
            'start_date' => $detail->start_date,
            'end_date' => $detail->end_date,
            'start_time' => $detail->start_time,
            'end_time' => $detail->end_time,
            'description' => $header->description,
            'status' => $header->status,
            'approved_atasan_by' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_by_name : null,
            'approved_atasan_at' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_at : null,
            'created_by' => $header->created_by_name,
            'created_at' => $header->created_at,
            'updated_by' => $header->updated_by_name,
            'updated_at' => $header->updated_at,
            'is_active' => $header->is_active ? 1 : 0,
        ]);

        foreach ($header->overtimeParticipants as $participant) {
            DB::connection($conn)->table('overtime_request_members')->insert([
                'overtime_request_id' => $legacyId,
                'no_document' => $header->no_document,
                'employee_id' => $participant->karyawan_id,
                'created_by' => $header->created_by_name,
                'created_at' => $header->created_at,
                'updated_by' => $header->updated_by_name,
                'updated_at' => $header->updated_at,
                'is_active' => $participant->is_active ? 1 : 0,
            ]);
        }

        $this->mapLegacy('overtime_requests', $legacyId, $header->id);
    }

    private function mirrorCreateAttendanceCorrection(HrRequest $header): void
    {
        $detail = $header->attendanceCorrectionDetail;
        if (!$detail) {
            return;
        }

        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $legacyId = DB::connection($conn)->table('attendance_corrections')->insertGetId([
            'employee_id' => $header->karyawan_id,
            'type' => $detail->correction_type,
            'date' => $detail->correction_date,
            'time' => $detail->correction_time,
            'description' => $header->description,
            'attachment' => $detail->attachment_path ?? '',
            'status' => $header->status,
            'approved_atasan_by' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_by_name : null,
            'approved_atasan_at' => $header->status === WorkflowStatus::APPROVED_ATASAN ? $header->updated_at : null,
            'created_by' => $header->created_by_name,
            'created_at' => $header->created_at,
            'updated_by' => $header->updated_by_name,
            'updated_at' => $header->updated_at,
            'is_active' => $header->is_active ? 1 : 0,
        ]);

        $this->mapLegacy('attendance_corrections', $legacyId, $header->id);
    }

    private function mapLegacy(string $oldTable, int $oldId, int $newId): void
    {
        HrMigrationMap::create([
            'old_connection' => 'intilab_apps',
            'old_table' => $oldTable,
            'old_id' => $oldId,
            'new_table' => 'hr_request',
            'new_id' => $newId,
            'migrated_at' => Carbon::now(),
        ]);
    }
}
