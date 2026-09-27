<?php

namespace App\Services\Hr\Portal;

use App\Models\Hr\HrRequest;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortalHrdIzinDatatableQuery
{
    public function unprocessed(int $periode): Collection
    {
        $permissions = $this->permissionRows($periode, false);
        $leaves = $this->leaveRows($periode, false);

        return $permissions->merge($leaves);
    }

    public function processed(int $periode): Collection
    {
        $permissions = $this->permissionRows($periode, true);
        $leaves = $this->leaveRows($periode, true);

        return $permissions->merge($leaves);
    }

    private function permissionRows(int $periode, bool $processed): Collection
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
            ->where(function ($q) {
                $q->where('u.atasan_langsung', 'NOT LIKE', '%"1"%')
                    ->orWhereNull('u.atasan_langsung');
            })
            ->whereNotNull(DB::raw(PortalHrApprovalStepSql::scalar('pr', 'atasan', 'approved', 'actor_name')));

        if ($processed) {
            $query->where('pr.status', WorkflowStatus::APPROVED_HRD);
        } else {
            $query->where('pr.status', WorkflowStatus::APPROVED_ATASAN);
        }

        return collect($query->select($this->permissionSelect())->get());
    }

    private function leaveRows(int $periode, bool $processed): Collection
    {
        $query = DB::table('hr_request as lr')
            ->join('hr_leave_detail as ld', 'ld.request_id', '=', 'lr.id')
            ->leftJoin('hr_migration_map as legacy_map', function ($join) {
                $join->on('legacy_map.new_id', '=', 'lr.id')
                    ->where('legacy_map.old_table', '=', 'leave_requests')
                    ->where('legacy_map.new_table', '=', 'hr_request')
                    ->where('legacy_map.old_connection', '=', 'intilab_apps');
            })
            ->leftJoin('master_karyawan as u', 'lr.karyawan_id', '=', 'u.id')
            ->leftJoin('master_divisi as d', 'u.id_department', '=', 'd.id')
            ->where('lr.request_type', HrRequest::TYPE_LEAVE)
            ->where('lr.is_active', true)
            ->whereYear('lr.created_at', $periode ?: date('Y'))
            ->where(function ($q) {
                $q->where('u.atasan_langsung', 'NOT LIKE', '%"1"%')
                    ->orWhereNull('u.atasan_langsung');
            })
            ->whereNotNull(DB::raw(PortalHrApprovalStepSql::scalar('lr', 'atasan', 'approved', 'actor_name')));

        if ($processed) {
            $query->where('lr.status', WorkflowStatus::APPROVED_HRD);
        } else {
            $query->where('lr.status', WorkflowStatus::APPROVED_ATASAN);
        }

        return collect($query->select($this->leaveSelect())->get());
    }

    private function permissionSelect(): array
    {
        $statusCase = $this->hrdStatusCase('pr', false);

        return [
            DB::raw("CONCAT('PR-', COALESCE(legacy_map.old_id, pr.id)) as id"),
            'pr.no_document',
            'd.nama_divisi',
            DB::raw($statusCase . ' as status'),
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
            'pr.created_by_name as nama_pengaju',
            'pd.attachment_path as filename',
            DB::raw('NULL as nama_delegasi'),
            'pr.created_at as diajukan_pada',
        ];
    }

    private function leaveSelect(): array
    {
        $statusCase = $this->hrdStatusCase('lr', true);

        return [
            DB::raw("CONCAT('LR-', COALESCE(legacy_map.old_id, lr.id)) as id"),
            'lr.no_document',
            'd.nama_divisi',
            DB::raw($statusCase . ' as status'),
            DB::raw("CASE ld.leave_kind
                WHEN 'special' THEN 'cuti_khusus'
                WHEN 'unpaid' THEN 'unpaid_leave'
                ELSE 'cuti'
            END as type_document"),
            'ld.start_date as tanggal_mulai',
            'ld.end_date as tanggal_selesai',
            DB::raw('NULL as jam_mulai'),
            DB::raw('NULL as jam_selesai'),
            'lr.description as keterangan',
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'atasan', 'approved', 'actor_name') . ' as approved_atasan_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'atasan', 'approved', 'acted_at') . ' as approved_atasan_at'),
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'hrd', 'approved', 'actor_name') . ' as approved_hrd_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'hrd', 'approved', 'acted_at') . ' as approved_hrd_at'),
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'atasan', 'rejected', 'actor_name') . ' as rejected_atasan_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'atasan', 'rejected', 'acted_at') . ' as rejected_atasan_at'),
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'hrd', 'rejected', 'actor_name') . ' as rejected_hrd_by'),
            DB::raw(PortalHrApprovalStepSql::scalar('lr', 'hrd', 'rejected', 'acted_at') . ' as rejected_hrd_at'),
            'lr.created_by_name as nama_pengaju',
            'ld.attachment_path as filename',
            DB::raw('NULL as nama_delegasi'),
            'lr.created_at as diajukan_pada',
        ];
    }

    private function hrdStatusCase(string $alias, bool $isLeave): string
    {
        if ($isLeave) {
            return 'CASE
                WHEN ' . $alias . '.status = "Approved Atasan" THEN "APPROVED"
                WHEN ' . $alias . '.status = "Approved HRD" THEN "APPROVED HRD"
                WHEN ' . $alias . '.status = "Rejected Atasan" THEN "REJECTED"
                WHEN ' . $alias . '.status = "Rejected HRD" THEN "REJECTED HRD"
                ELSE "WAITING"
            END';
        }

        return 'CASE
            WHEN ' . $alias . '.status = "Approved Atasan" THEN "APPROVED ATASAN"
            WHEN ' . $alias . '.status = "Approved HRD" THEN "APPROVED HRD"
            WHEN ' . $alias . '.status = "Rejected Atasan" THEN "REJECTED ATASAN"
            WHEN ' . $alias . '.status = "Rejected HRD" THEN "REJECTED HRD"
            ELSE "WAITING"
        END';
    }
}
