<?php

namespace App\Services\Hr\Portal;

use App\Models\Hr\HrRequest;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PortalOvertimeDatatableQuery
{
    public function hrdUnprocessed(int $periode): Collection
    {
        return $this->fetchGrouped($periode, function ($q) {
            $q->where('overtime_requests.status', WorkflowStatus::APPROVED_ATASAN)
                ->whereRaw(PortalHrApprovalStepSql::noPendingAtasanSteps('overtime_requests'));
        });
    }

    public function hrdProcessed(int $periode): Collection
    {
        return $this->fetchGrouped($periode, function ($q) {
            $q->where('overtime_requests.status', WorkflowStatus::APPROVED_HRD);
        });
    }

    public function financeUnprocessed(int $periode): Collection
    {
        return $this->fetchGrouped($periode, function ($q) {
            $q->where('overtime_requests.status', WorkflowStatus::APPROVED_HRD);
        });
    }

    public function financeProcessed(int $periode): Collection
    {
        return $this->fetchGrouped($periode, function ($q) {
            $q->where('overtime_requests.status', WorkflowStatus::APPROVED_FINANCE);
        });
    }

    public function ownerUnprocessed(int $periode, array $creatorNames): Collection
    {
        return $this->fetchGrouped($periode, function ($q) use ($creatorNames) {
            $q->whereIn('overtime_requests.created_by_name', $creatorNames)
                ->whereNotIn('overtime_requests.status', [
                    WorkflowStatus::APPROVED_HRD,
                    WorkflowStatus::APPROVED_FINANCE,
                    WorkflowStatus::REJECTED_ATASAN,
                    WorkflowStatus::REJECTED_HRD,
                    WorkflowStatus::REJECTED_FINANCE,
                ]);
        });
    }

    public function ownerProcessed(int $periode, array $creatorNames): Collection
    {
        return $this->fetchGrouped($periode, function ($q) use ($creatorNames) {
            $q->whereIn('overtime_requests.created_by_name', $creatorNames)
                ->whereIn('overtime_requests.status', [
                    WorkflowStatus::APPROVED_HRD,
                    WorkflowStatus::APPROVED_FINANCE,
                    WorkflowStatus::REJECTED_ATASAN,
                    WorkflowStatus::REJECTED_HRD,
                    WorkflowStatus::REJECTED_FINANCE,
                ]);
        });
    }

    private function fetchGrouped(int $periode, callable $scope): Collection
    {
        $query = DB::table('hr_request as overtime_requests')
            ->join('hr_overtime_detail as od', 'od.request_id', '=', 'overtime_requests.id')
            ->leftJoin('hr_migration_map as legacy_map', function ($join) {
                $join->on('legacy_map.new_id', '=', 'overtime_requests.id')
                    ->where('legacy_map.old_table', '=', 'overtime_requests')
                    ->where('legacy_map.new_table', '=', 'hr_request')
                    ->where('legacy_map.old_connection', '=', 'intilab_apps');
            })
            ->leftJoin('master_divisi as d', 'd.id', '=', 'overtime_requests.id_department')
            ->leftJoin('hr_overtime_participant as fd', function ($join) {
                $join->on('fd.request_id', '=', 'overtime_requests.id')
                    ->where('fd.is_active', true);
            })
            ->leftJoin('master_karyawan as u', 'fd.karyawan_id', '=', 'u.id')
            ->where('overtime_requests.request_type', HrRequest::TYPE_OVERTIME)
            ->where('overtime_requests.is_active', true)
            ->whereYear('od.start_date', $periode ?: date('Y'));

        $scope($query);

        $rows = $query
            ->select(
                DB::raw('COALESCE(legacy_map.old_id, overtime_requests.id) as id'),
                'overtime_requests.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                    WHEN overtime_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                    WHEN overtime_requests.status = "Approved HRD" THEN "Approved HRD" 
                    WHEN overtime_requests.status = "Approved Finance" THEN "Approved Finance" 
                    WHEN overtime_requests.status = "Rejected Atasan" THEN "Rejected Atasan" 
                    WHEN overtime_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                    WHEN overtime_requests.status = "Rejected Finance" THEN "Rejected Finance" 
                    ELSE "Pending" 
                END as status'),
                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw('COUNT(DISTINCT fd.karyawan_id) as total_karyawan'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'hrd', 'approved', 'actor_name') . ' as approved_hrd_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'hrd', 'approved', 'acted_at') . ' as approved_hrd_at'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'finance', 'approved', 'actor_name') . ' as approved_finance_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'finance', 'approved', 'acted_at') . ' as approved_finance_at'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'atasan', 'approved', 'actor_name') . ' as approved_atasan_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'atasan', 'approved', 'acted_at') . ' as approved_atasan_at'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'atasan', 'rejected', 'actor_name') . ' as rejected_atasan_by'),
                DB::raw(PortalHrApprovalStepSql::scalar('overtime_requests', 'atasan', 'rejected', 'acted_at') . ' as rejected_atasan_at'),
                'od.start_date as tanggal',
                'od.start_time as jam_mulai',
                'od.end_time as jam_selesai',
                'overtime_requests.created_by_name as nama_pengaju',
                'overtime_requests.created_at as diajukan_pada',
                'overtime_requests.description as keterangan'
            )
            ->groupBy(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                'overtime_requests.status',
                'legacy_map.old_id',
                'od.start_date',
                'od.start_time',
                'od.end_time',
                'overtime_requests.created_by_name',
                'overtime_requests.created_at',
                'overtime_requests.description'
            )
            ->get();

        return $rows->map(function ($item) {
            if (!empty($item->karyawan)) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
            } else {
                $item->karyawan = [];
            }

            return $item;
        });
    }
}
