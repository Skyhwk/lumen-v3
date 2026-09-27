<?php

namespace App\Services\Hr\Portal;

use App\Models\Hr\HrRequest;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortalPermissionDatatableQuery
{
    public function unprocessed(int $periode, array $ownerNames, ?string $staffKaryawanName = null): Collection
    {
        $query = $this->baseQuery($periode, $ownerNames)
            ->whereNotIn('pr.status', [
                WorkflowStatus::APPROVED_HRD,
                WorkflowStatus::REJECTED_HRD,
                WorkflowStatus::REJECTED_ATASAN,
            ]);

        if ($staffKaryawanName) {
            $query->where('pr.created_by_name', $staffKaryawanName);
        }

        return collect($query->get());
    }

    public function processed(int $periode, array $ownerNames, ?string $staffKaryawanName = null): Collection
    {
        $query = $this->baseQuery($periode, $ownerNames)
            ->whereIn('pr.status', [
                WorkflowStatus::APPROVED_HRD,
                WorkflowStatus::REJECTED_HRD,
                WorkflowStatus::REJECTED_ATASAN,
            ]);

        if ($staffKaryawanName) {
            $query->where('pr.created_by_name', $staffKaryawanName);
        }

        return collect($query->get());
    }

    public function countUnprocessed(int $periode, array $ownerNames, ?string $staffKaryawanName = null): int
    {
        return $this->unprocessed($periode, $ownerNames, $staffKaryawanName)->count();
    }

    public function countProcessed(int $periode, array $ownerNames, ?string $staffKaryawanName = null): int
    {
        return $this->processed($periode, $ownerNames, $staffKaryawanName)->count();
    }

    private function baseQuery(int $periode, array $ownerNames)
    {
        $query = DB::table('hr_request as pr')
            ->join('hr_permission_detail as pd', 'pd.request_id', '=', 'pr.id')
            ->leftJoin('hr_migration_map as legacy_map', function ($join) {
                $join->on('legacy_map.new_id', '=', 'pr.id')
                    ->where('legacy_map.old_table', '=', 'permission_requests')
                    ->where('legacy_map.new_table', '=', 'hr_request')
                    ->where('legacy_map.old_connection', '=', 'intilab_apps');
            })
            ->leftJoin('master_karyawan as u', 'pr.karyawan_id', '=', 'u.id')
            ->leftJoin('master_divisi as d', 'u.id_department', '=', 'd.id')
            ->where('pr.request_type', HrRequest::TYPE_PERMISSION)
            ->where('pr.is_active', true)
            ->whereYear('pr.created_at', $periode ?: date('Y'))
            ->whereIn('pr.created_by_name', $ownerNames)
            ->select(
                DB::raw('COALESCE(legacy_map.old_id, pr.id) as id'),
                'pr.no_document',
                DB::raw("CASE pd.permission_kind
                    WHEN 'sick' THEN 'Sick Leave'
                    WHEN 'late' THEN 'Late Arrival'
                    ELSE 'Event Leave'
                END as type"),
                'pd.start_date',
                'pd.end_date',
                'pd.start_time',
                'pd.end_time',
                'pr.description',
                'pd.attachment_path as attachment',
                'pr.status',
                'pr.created_by_name as nama_pengaju',
                'pr.created_at as diajukan_pada',
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'approved', 'actor_name') . ' as approved_atasan_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'approved', 'acted_at') . ' as approved_atasan_at'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'rejected', 'actor_name') . ' as rejected_atasan_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'rejected', 'acted_at') . ' as rejected_atasan_at'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'rejected', 'reason') . ' as reject_atasan_reason'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'approved', 'actor_name') . ' as approved_hrd_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'approved', 'acted_at') . ' as approved_hrd_at'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'rejected', 'actor_name') . ' as rejected_hrd_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'rejected', 'acted_at') . ' as rejected_hrd_at'),
                DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'rejected', 'reason') . ' as reject_hrd_reason'),
                'd.nama_divisi'
            );

        return $query;
    }
}
