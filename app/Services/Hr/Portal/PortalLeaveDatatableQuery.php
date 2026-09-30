<?php

namespace App\Services\Hr\Portal;

use App\Models\Hr\HrRequest;
use App\Services\Hr\WorkflowStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PortalLeaveDatatableQuery
{
    public function unprocessed(int $year, array $bawahanIds, array $bawahanNames): Builder
    {
        $query = $this->baseQuery($year, $bawahanIds, $bawahanNames);

        return $query
            ->whereNotIn('leave_requests.status', [
                WorkflowStatus::APPROVED_HRD,
                WorkflowStatus::REJECTED_ATASAN,
                WorkflowStatus::REJECTED_HRD,
            ]);
    }

    public function processed(int $year, array $bawahanIds, array $bawahanNames): Builder
    {
        $query = $this->baseQuery($year, $bawahanIds, $bawahanNames);

        return $query->whereIn('leave_requests.status', [
            WorkflowStatus::APPROVED_HRD,
            WorkflowStatus::REJECTED_ATASAN,
            WorkflowStatus::REJECTED_HRD,
        ]);
    }

    public function countUnprocessed(int $year, array $bawahanIds, array $bawahanNames): int
    {
        return (int) $this->unprocessed($year, $bawahanIds, $bawahanNames)
            ->select(DB::raw('1'))
            ->count();
    }

    public function countProcessed(int $year, array $bawahanIds, array $bawahanNames): int
    {
        return (int) $this->processed($year, $bawahanIds, $bawahanNames)
            ->select(DB::raw('1'))
            ->count();
    }

    /** Antrian HRD: hanya setelah seluruh rantai atasan selesai. */
    public function hrdUnprocessed(int $year): Builder
    {
        return $this->hrdBaseQuery($year)
            ->where('leave_requests.status', WorkflowStatus::APPROVED_ATASAN)
            ->whereRaw(PortalHrApprovalStepSql::noPendingAtasanSteps('leave_requests'));
    }

    public function hrdProcessed(int $year): Builder
    {
        return $this->hrdBaseQuery($year)->whereIn('leave_requests.status', [
            WorkflowStatus::APPROVED_HRD,
            WorkflowStatus::REJECTED_ATASAN,
            WorkflowStatus::REJECTED_HRD,
        ]);
    }

    public function countHrdUnprocessed(int $year): int
    {
        return (int) $this->hrdUnprocessed($year)->select(DB::raw('1'))->count();
    }

    public function countHrdProcessed(int $year): int
    {
        return (int) $this->hrdProcessed($year)->select(DB::raw('1'))->count();
    }

    private function hrdBaseQuery(int $year): Builder
    {
        return DB::table('hr_request as leave_requests')
            ->join('hr_leave_detail as lrd', 'lrd.request_id', '=', 'leave_requests.id')
            ->leftJoin('hr_migration_map as legacy_map', function ($join) {
                $join->on('legacy_map.new_id', '=', 'leave_requests.id')
                    ->where('legacy_map.old_table', '=', 'leave_requests')
                    ->where('legacy_map.new_table', '=', 'hr_request')
                    ->where('legacy_map.old_connection', '=', 'intilab_apps');
            })
            ->leftJoin('master_karyawan as karyawan', 'leave_requests.karyawan_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('hr_special_leave_type as slt', 'lrd.special_leave_type_id', '=', 'slt.id')
            ->where('leave_requests.request_type', HrRequest::TYPE_LEAVE)
            ->where('leave_requests.is_active', true)
            ->whereYear('lrd.start_date', $year)
            ->where(function ($q) {
                $q->where('karyawan.atasan_langsung', 'NOT LIKE', '%"1"%')
                    ->orWhereNull('karyawan.atasan_langsung');
            })
            ->select(
                DB::raw('COALESCE(legacy_map.old_id, leave_requests.id) as id'),
                'leave_requests.no_document',
                DB::raw("CASE lrd.leave_kind
                    WHEN 'special' THEN 'Special Leave'
                    WHEN 'unpaid' THEN 'Unpaid Leave'
                    WHEN 'phl' THEN 'Holiday Replacement Leave'
                    WHEN 'urgent' THEN 'Urgent Leave'
                    ELSE 'Annual Leave'
                END as type"),
                'lrd.special_leave_type_id as special_leave_id',
                'slt.name as special_leave_name',
                'lrd.start_date',
                'lrd.end_date',
                'lrd.start_date as tanggal',
                'd.nama_divisi',
                DB::raw('CASE
                    WHEN leave_requests.status = "Approved Atasan" THEN "Approve Atasan"
                    WHEN leave_requests.status = "Rejected Atasan" THEN "Rejected Atasan"
                    WHEN leave_requests.status = "Approved HRD" THEN "Approved HRD"
                    WHEN leave_requests.status = "Rejected HRD" THEN "Rejected HRD"
                    ELSE "Pending"
                END as status'),
                'karyawan.id as employee_id',
                'karyawan.nama_lengkap',
                'karyawan.grade as jabatan',
                DB::raw($this->stepScalar('atasan', 'approved', 'actor_name') . ' as approved_atasan_by'),
                DB::raw($this->stepScalar('atasan', 'approved', 'acted_at') . ' as approved_atasan_at'),
                DB::raw($this->stepScalar('atasan', 'rejected', 'actor_name') . ' as rejected_atasan_by'),
                DB::raw($this->stepScalar('atasan', 'rejected', 'acted_at') . ' as rejected_atasan_at'),
                DB::raw($this->stepScalar('atasan', 'rejected', 'reason') . ' as reject_atasan_reason'),
                DB::raw($this->stepScalar('hrd', 'approved', 'actor_name') . ' as approved_hrd_by'),
                DB::raw($this->stepScalar('hrd', 'approved', 'acted_at') . ' as approved_hrd_at'),
                DB::raw($this->stepScalar('hrd', 'rejected', 'actor_name') . ' as rejected_hrd_by'),
                DB::raw($this->stepScalar('hrd', 'rejected', 'acted_at') . ' as rejected_hrd_at'),
                DB::raw($this->stepScalar('hrd', 'rejected', 'reason') . ' as reject_hrd_reason'),
                'lrd.attachment_path as attachment',
                'leave_requests.created_by_name as nama_pengaju',
                'leave_requests.created_at as diajukan_pada',
                'leave_requests.description as keterangan',
                'leave_requests.status as raw_status'
            );
    }

    private function baseQuery(int $year, array $bawahanIds, array $bawahanNames): Builder
    {
        $query = DB::table('hr_request as leave_requests')
            ->join('hr_leave_detail as lrd', 'lrd.request_id', '=', 'leave_requests.id')
            ->leftJoin('hr_migration_map as legacy_map', function ($join) {
                $join->on('legacy_map.new_id', '=', 'leave_requests.id')
                    ->where('legacy_map.old_table', '=', 'leave_requests')
                    ->where('legacy_map.new_table', '=', 'hr_request')
                    ->where('legacy_map.old_connection', '=', 'intilab_apps');
            })
            ->leftJoin('master_karyawan as karyawan', 'leave_requests.karyawan_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('hr_special_leave_type as slt', 'lrd.special_leave_type_id', '=', 'slt.id')
            ->where('leave_requests.request_type', HrRequest::TYPE_LEAVE)
            ->where('leave_requests.is_active', true)
            ->whereYear('lrd.start_date', $year)
            ->select(
                DB::raw('COALESCE(legacy_map.old_id, leave_requests.id) as id'),
                'leave_requests.no_document',
                DB::raw("CASE lrd.leave_kind
                    WHEN 'special' THEN 'Special Leave'
                    WHEN 'unpaid' THEN 'Unpaid Leave'
                    WHEN 'phl' THEN 'Holiday Replacement Leave'
                    WHEN 'urgent' THEN 'Urgent Leave'
                    ELSE 'Annual Leave'
                END as type"),
                'lrd.special_leave_type_id as special_leave_id',
                'slt.name as special_leave_name',
                'lrd.start_date',
                'lrd.end_date',
                'lrd.start_date as tanggal',
                'd.nama_divisi',
                DB::raw('CASE
                    WHEN leave_requests.status = "Approved Atasan" THEN "Approve Atasan"
                    WHEN leave_requests.status = "Rejected Atasan" THEN "Rejected Atasan"
                    WHEN leave_requests.status = "Approved HRD" THEN "Approved HRD"
                    WHEN leave_requests.status = "Rejected HRD" THEN "Rejected HRD"
                    ELSE "Pending"
                END as status'),
                'karyawan.id as employee_id',
                'karyawan.nama_lengkap',
                'karyawan.grade as jabatan',
                DB::raw($this->stepScalar('atasan', 'approved', 'actor_name') . ' as approved_atasan_by'),
                DB::raw($this->stepScalar('atasan', 'approved', 'acted_at') . ' as approved_atasan_at'),
                DB::raw($this->stepScalar('atasan', 'rejected', 'actor_name') . ' as rejected_atasan_by'),
                DB::raw($this->stepScalar('atasan', 'rejected', 'acted_at') . ' as rejected_atasan_at'),
                DB::raw($this->stepScalar('atasan', 'rejected', 'reason') . ' as reject_atasan_reason'),
                DB::raw($this->stepScalar('hrd', 'approved', 'actor_name') . ' as approved_hrd_by'),
                DB::raw($this->stepScalar('hrd', 'approved', 'acted_at') . ' as approved_hrd_at'),
                DB::raw($this->stepScalar('hrd', 'rejected', 'actor_name') . ' as rejected_hrd_by'),
                DB::raw($this->stepScalar('hrd', 'rejected', 'acted_at') . ' as rejected_hrd_at'),
                DB::raw($this->stepScalar('hrd', 'rejected', 'reason') . ' as reject_hrd_reason'),
                'lrd.attachment_path as attachment',
                'leave_requests.created_by_name as nama_pengaju',
                'leave_requests.created_at as diajukan_pada',
                'leave_requests.description as keterangan',
                'leave_requests.status as raw_status'
            );

        if (!empty($bawahanIds)) {
            $query->where(function ($q) use ($bawahanIds, $bawahanNames) {
                $q->whereIn('leave_requests.karyawan_id', $bawahanIds);
                if (!empty($bawahanNames)) {
                    $q->orWhereIn('leave_requests.created_by_name', $bawahanNames);
                }
            });
        }

        return $query;
    }

    private function stepScalar(string $step, string $state, string $column): string
    {
        return "(SELECT s.{$column} FROM hr_approval_step s
            WHERE s.request_id = leave_requests.id AND s.step = '{$step}' AND s.state = '{$state}'
            ORDER BY s.id DESC LIMIT 1)";
    }

    public function applyDatatablesFilters($datatables)
    {
        return $datatables
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('karyawan.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('type', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('lrd.leave_kind', 'like', "%{$keyword}%")
                        ->orWhere('slt.name', 'like', "%{$keyword}%");
                    if (stripos('cuti tahunan', $keyword) !== false || stripos('annual leave', $keyword) !== false) {
                        $sub->orWhere('lrd.leave_kind', 'annual');
                    }
                    if (stripos('cuti khusus', $keyword) !== false || stripos('special leave', $keyword) !== false) {
                        $sub->orWhere('lrd.leave_kind', 'special');
                    }
                    if (stripos('cuti mendesak', $keyword) !== false || stripos('urgent leave', $keyword) !== false) {
                        $sub->orWhere('lrd.leave_kind', 'urgent');
                    }
                });
            })
            ->filterColumn('nama_divisi', function ($query, $keyword) {
                $query->where('d.nama_divisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_pengaju', function ($query, $keyword) {
                $query->where('leave_requests.created_by_name', 'like', "%{$keyword}%");
            })
            ->filterColumn('keterangan', function ($query, $keyword) {
                $query->where('leave_requests.description', 'like', "%{$keyword}%");
            })
            ->filterColumn('status', function ($query, $keyword) {
                $query->where('leave_requests.status', 'like', "%{$keyword}%");
            })
            ->filterColumn('start_date', function ($query, $keyword) {
                $query->where('lrd.start_date', 'like', "%{$keyword}%");
            })
            ->filterColumn('end_date', function ($query, $keyword) {
                $query->where('lrd.end_date', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal', function ($query, $keyword) {
                $query->where('lrd.start_date', 'like', "%{$keyword}%");
            })
            ->filterColumn('special_leave_name', function ($query, $keyword) {
                $query->where('slt.name', 'like', "%{$keyword}%");
            })
            ->orderColumn('nama_lengkap', function ($query, $order) {
                $query->orderBy('karyawan.nama_lengkap', $order);
            })
            ->orderColumn('nama_divisi', function ($query, $order) {
                $query->orderBy('d.nama_divisi', $order);
            })
            ->orderColumn('nama_pengaju', function ($query, $order) {
                $query->orderBy('leave_requests.created_by_name', $order);
            })
            ->orderColumn('keterangan', function ($query, $order) {
                $query->orderBy('leave_requests.description', $order);
            });
    }
}
