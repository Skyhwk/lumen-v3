<?php

namespace App\Services\Hr\Portal;

use App\Models\Hr\HrRequest;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortalHrdIzinDatatableQuery
{
    public function unprocessed(int $periode): Collection
    {
        return $this->byStatus($periode, WorkflowStatus::APPROVED_ATASAN);
    }

    public function processed(int $periode): Collection
    {
        return $this->byStatus($periode, WorkflowStatus::APPROVED_HRD);
    }

    public function byStatus(int $periode, string $status): Collection
    {
        $query = $this->basePermissionQuery($periode, $status);

        return collect($query->select($this->permissionSelect())->get());
    }

    public function countByStatus(int $periode, string $status): int
    {
        return (int) $this->basePermissionQuery($periode, $status)->count();
    }

    private function basePermissionQuery(int $periode, string $status): Builder
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
            ->whereYear('pr.created_at', $periode ?: (int) date('Y'))
            ->where('pr.status', $status);

        if ($status === WorkflowStatus::APPROVED_ATASAN) {
            $query->where(function ($q) {
                $q->where('u.atasan_langsung', 'NOT LIKE', '%"1"%')
                    ->orWhereNull('u.atasan_langsung');
            })->whereRaw(PortalHrApprovalStepSql::noPendingAtasanSteps('pr'));
        } elseif ($status === WorkflowStatus::REJECTED_HRD || $status === WorkflowStatus::PENDING) {
            $query->where(function ($q) {
                $q->where('u.atasan_langsung', 'NOT LIKE', '%"1"%')
                    ->orWhereNull('u.atasan_langsung');
            });
        }

        return $query;
    }

    private function permissionSelect(): array
    {
        $statusCase = $this->hrdStatusCase('pr');

        return [
            DB::raw("CONCAT('PR-', COALESCE(legacy_map.old_id, pr.id)) as id"),
            'pr.no_document',
            'd.nama_divisi',
            DB::raw($statusCase . ' as status'),
            DB::raw('NULL as special_leave_name'),
            DB::raw("CASE pd.permission_kind
                WHEN 'sick' THEN 'sakit'
                WHEN 'late' THEN 'datang_terlambat'
                ELSE 'kegiatan'
            END as type_document"),
            'pd.start_date as tanggal_mulai',
            'pd.end_date as tanggal_selesai',
            'pd.start_time as jam_mulai',
            'pd.end_time as jam_selesai',
            'pr.description as keterangan',
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'approved', 'actor_name') . ' as approved_atasan_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'approved', 'acted_at') . ' as approved_atasan_at'),
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'approved', 'actor_name') . ' as approved_hrd_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'approved', 'acted_at') . ' as approved_hrd_at'),
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'rejected', 'actor_name') . ' as rejected_atasan_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'rejected', 'acted_at') . ' as rejected_atasan_at'),
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'rejected', 'actor_name') . ' as rejected_hrd_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('pr', 'hrd', 'rejected', 'acted_at') . ' as rejected_hrd_at'),
            'u.nama_lengkap as nama_karyawan',
            'pr.created_by_name as nama_pengaju',
            'pd.attachment_path as filename',
            DB::raw('NULL as nama_delegasi'),
            'pr.created_at as diajukan_pada',
        ];
    }

    private function hrdStatusCase(string $alias): string
    {
        return 'CASE
            WHEN ' . $alias . '.status = "Pending" THEN "WAITING"
            WHEN ' . $alias . '.status = "Approved Atasan" THEN "APPROVED ATASAN"
            WHEN ' . $alias . '.status = "Approved HRD" THEN "APPROVED HRD"
            WHEN ' . $alias . '.status = "Rejected Atasan" THEN "REJECTED ATASAN"
            WHEN ' . $alias . '.status = "Rejected HRD" THEN "REJECTED HRD"
            ELSE "WAITING"
        END';
    }
}
