<?php

namespace App\Services\Hr;

use App\Models\Hr\HrApprovalStep;
use App\Models\Hr\HrLeaveDetail;
use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrOvertimeDetail;
use App\Models\Hr\HrOvertimeParticipant;
use App\Models\Hr\HrPermissionDetail;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sinkron legacy intilab_apps → hr_* saat portal V3 mengubah data.
 */
class PortalHrSync
{
    public function syncLeaveFromLegacy(int $legacyLeaveId): void
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $row = DB::connection($conn)->table('leave_requests')->where('id', $legacyLeaveId)->first();
        if (!$row) {
            return;
        }

        $hr = $this->resolveOrCreateHrHeader('leave_requests', HrRequest::TYPE_LEAVE, $legacyLeaveId, $row);

        $karyawan = MasterKaryawan::find((int) $row->employee_id);
        $hr->fill([
            'karyawan_id' => (int) $row->employee_id,
            'id_cabang' => $karyawan->id_cabang ?? null,
            'id_department' => $karyawan->id_department ?? null,
            'no_document' => $row->no_document,
            'status' => $row->status,
            'description' => $row->description,
            'created_by_name' => $row->created_by,
            'updated_by_name' => $row->updated_by,
            'updated_at' => $row->updated_at,
            'is_active' => (bool) $row->is_active,
        ]);
        $hr->save();

        HrLeaveDetail::updateOrCreate(
            ['request_id' => $hr->id],
            [
                'leave_kind' => HrLegacyFieldMapper::leaveKindFromType($row->type),
                'special_leave_type_id' => $this->hrSpecialLeaveIdFromLegacy($row->special_leave_id),
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'attachment_path' => $row->attachment,
            ]
        );

        $this->rebuildApprovalStepsFromLegacy($hr->id, $row, false);
    }

    public function syncPermissionFromLegacy(int $legacyId): void
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $row = DB::connection($conn)->table('permission_requests')->where('id', $legacyId)->first();
        if (!$row) {
            return;
        }

        $karyawanId = $this->resolvePermissionKaryawanId($row);
        if (!$karyawanId) {
            return;
        }

        $hr = $this->resolveOrCreateHrHeader('permission_requests', HrRequest::TYPE_PERMISSION, $legacyId, $row);
        $karyawan = MasterKaryawan::find($karyawanId);

        $hr->fill([
            'karyawan_id' => $karyawanId,
            'id_cabang' => $karyawan->id_cabang ?? null,
            'id_department' => $karyawan->id_department ?? null,
            'no_document' => $row->no_document,
            'status' => $row->status,
            'description' => $row->description,
            'created_by_name' => $row->created_by,
            'updated_by_name' => $row->updated_by,
            'updated_at' => $row->updated_at,
            'is_active' => (bool) $row->is_active,
        ]);
        $hr->save();

        HrPermissionDetail::updateOrCreate(
            ['request_id' => $hr->id],
            [
                'permission_kind' => HrLegacyFieldMapper::permissionKindFromType($row->type),
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'attachment_path' => $row->attachment,
            ]
        );

        $this->rebuildApprovalStepsFromLegacy($hr->id, $row, false);
    }

    public function syncOvertimeFromLegacy(int $legacyId): void
    {
        $conn = config('greatday.legacy_apps_connection', 'intilab_apps');
        $row = DB::connection($conn)->table('overtime_requests')->where('id', $legacyId)->first();
        if (!$row) {
            return;
        }

        $hr = $this->resolveOrCreateHrHeader('overtime_requests', HrRequest::TYPE_OVERTIME, $legacyId, $row);

        $hr->fill([
            'id_department' => $row->department_id,
            'no_document' => $row->no_document,
            'status' => $row->status,
            'description' => $row->description,
            'created_by_name' => $row->created_by,
            'updated_by_name' => $row->updated_by,
            'updated_at' => $row->updated_at,
            'is_active' => (bool) $row->is_active,
        ]);
        $hr->save();

        HrOvertimeDetail::updateOrCreate(
            ['request_id' => $hr->id],
            [
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
            ]
        );

        $members = DB::connection($conn)->table('overtime_request_members')
            ->where('overtime_request_id', $legacyId)
            ->get();

        foreach ($members as $member) {
            HrOvertimeParticipant::updateOrCreate(
                ['request_id' => $hr->id, 'karyawan_id' => $member->employee_id],
                [
                    'is_active' => (bool) $member->is_active,
                    'created_at' => $member->created_at,
                    'updated_at' => $member->updated_at,
                ]
            );
        }

        $this->rebuildApprovalStepsFromLegacy($hr->id, $row, true);
    }

    private function resolveOrCreateHrHeader(string $legacyTable, string $requestType, int $legacyId, $row): HrRequest
    {
        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $legacyTable,
            'old_id' => $legacyId,
        ])->first();

        if ($map) {
            return HrRequest::findOrFail($map->new_id);
        }

        $now = Carbon::now();
        $karyawanId = (int) ($row->employee_id ?? 0);
        if ($requestType === HrRequest::TYPE_PERMISSION) {
            $karyawanId = $this->resolvePermissionKaryawanId($row) ?: 0;
        }

        $header = HrRequest::create([
            'uuid' => (string) Str::uuid(),
            'request_type' => $requestType,
            'no_document' => $row->no_document,
            'karyawan_id' => $karyawanId,
            'status' => $row->status,
            'workflow_code' => $requestType === HrRequest::TYPE_OVERTIME ? 'overtime_3_step_finance' : 'default_2_step',
            'description' => $row->description ?? null,
            'submitted_at' => $row->created_at ?? $now,
            'created_by_karyawan_id' => $karyawanId ?: null,
            'created_by_name' => $row->created_by ?? 'Portal',
            'created_at' => $row->created_at ?? $now,
            'updated_by_name' => $row->updated_by ?? 'Portal',
            'updated_at' => $row->updated_at ?? $now,
            'is_active' => (bool) ($row->is_active ?? true),
        ]);

        HrMigrationMap::create([
            'old_connection' => 'intilab_apps',
            'old_table' => $legacyTable,
            'old_id' => $legacyId,
            'new_table' => 'hr_request',
            'new_id' => $header->id,
            'migrated_at' => $now,
        ]);

        return $header;
    }

    private function rebuildApprovalStepsFromLegacy(int $requestId, $row, bool $withFinance): void
    {
        HrApprovalStep::where('request_id', $requestId)->delete();

        $this->insertStepFromLegacy($requestId, HrApprovalStep::STEP_ATASAN, $row, 'atasan');
        $this->insertStepFromLegacy($requestId, HrApprovalStep::STEP_HRD, $row, 'hrd');
        if ($withFinance) {
            $this->insertStepFromLegacy($requestId, HrApprovalStep::STEP_FINANCE, $row, 'finance');
        }
    }

    private function insertStepFromLegacy(int $requestId, string $step, $row, string $prefix): void
    {
        $approvedBy = $row->{"approved_{$prefix}_by"} ?? null;
        $rejectedBy = $row->{"rejected_{$prefix}_by"} ?? null;

        if (!$approvedBy && !$rejectedBy) {
            if ($step === HrApprovalStep::STEP_ATASAN && ($row->status ?? '') === WorkflowStatus::PENDING) {
                HrApprovalStep::create([
                    'request_id' => $requestId,
                    'step' => $step,
                    'state' => HrApprovalStep::STATE_PENDING,
                ]);
            }

            return;
        }

        if ($approvedBy) {
            HrApprovalStep::create([
                'request_id' => $requestId,
                'step' => $step,
                'state' => HrApprovalStep::STATE_APPROVED,
                'actor_name' => $approvedBy,
                'acted_at' => $row->{"approved_{$prefix}_at"} ?? null,
            ]);
        } else {
            HrApprovalStep::create([
                'request_id' => $requestId,
                'step' => $step,
                'state' => HrApprovalStep::STATE_REJECTED,
                'actor_name' => $rejectedBy,
                'acted_at' => $row->{"rejected_{$prefix}_at"} ?? null,
                'reason' => $row->{"reject_{$prefix}_reason"} ?? null,
            ]);
        }
    }

    private function resolvePermissionKaryawanId($row): ?int
    {
        $byId = MasterKaryawan::where('id', (int) $row->employee_id)->value('id');
        if ($byId) {
            return (int) $byId;
        }

        $byUserId = MasterKaryawan::where('user_id', (int) $row->employee_id)->value('id');

        return $byUserId ? (int) $byUserId : null;
    }

    private function hrSpecialLeaveIdFromLegacy($legacyId): ?int
    {
        if (!$legacyId) {
            return null;
        }

        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => 'special_leave_types',
            'old_id' => $legacyId,
        ])->first();

        return $map ? (int) $map->new_id : (int) $legacyId;
    }
}
