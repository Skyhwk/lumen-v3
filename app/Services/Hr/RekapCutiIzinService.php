<?php

namespace App\Services\Hr;

use App\Models\Hr\HrLeaveBalanceLedger;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\LeaveAlpaAttendanceScope;
use App\Services\Greatday\LeaveBalanceService;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RekapCutiIzinService
{
    /** @var RekapLiburKalenderService */
    private $rekapLibur;

    /** @var array<string, array<string, int>> */
    private $memo = [];

    public function __construct(RekapLiburKalenderService $rekapLibur)
    {
        $this->rekapLibur = $rekapLibur;
    }

    /**
     * @return array{
     *   cuti_days: int,
     *   izin_sakit_days: int,
     *   izin_kegiatan_days: int,
     *   izin_terlambat_count: int,
     *   lembur_count: int,
     *   koreksi_kehadiran_count: int,
     *   alpa_days: int
     * }
     */
    /**
     * Prefetch metrik untuk sekumpulan karyawan (satu halaman DataTables).
     *
     * @param  iterable<int, MasterKaryawan>  $employees
     */
    public function primeMetricsForEmployees(
        iterable $employees,
        Carbon $from,
        Carbon $to,
        string $filterMode = 'tahun'
    ): void {
        $fromYmd = $from->copy()->startOfDay()->format('Y-m-d');
        $toYmd = $to->copy()->startOfDay()->format('Y-m-d');
        $modeKey = strtolower(trim($filterMode));
        $windowFrom = $from->copy()->startOfDay();
        $windowTo = $to->copy()->startOfDay();

        $byId = [];
        foreach ($employees as $employee) {
            $id = (int) $employee->id;
            if ($id <= 0) {
                continue;
            }
            $byId[$id] = $employee;
        }

        if ($byId === []) {
            return;
        }

        $ids = array_keys($byId);
        $memoKey = function (int $karyawanId) use ($fromYmd, $toYmd, $modeKey): string {
            return $karyawanId . ':' . $fromYmd . ':' . $toYmd . ':' . $modeKey;
        };

        $pendingIds = [];
        foreach ($ids as $id) {
            if (!isset($this->memo[$memoKey($id)])) {
                $pendingIds[] = $id;
            }
        }

        if ($pendingIds === []) {
            return;
        }

        for ($year = (int) $windowFrom->format('Y'); $year <= (int) $windowTo->format('Y'); $year++) {
            $this->rekapLibur->workingDayIndexForYear($year);
        }

        $models = MasterKaryawan::query()
            ->whereIn('id', $pendingIds)
            ->get()
            ->keyBy('id');
        foreach ($pendingIds as $id) {
            if ($models->has($id)) {
                $byId[$id] = $models[$id];
            }
        }

        $defaults = $this->emptyMetrics();
        foreach ($pendingIds as $id) {
            $this->memo[$memoKey($id)] = $defaults;
        }

        $permissionByKaryawan = $this->loadPermissionRowsByKaryawan($pendingIds, $fromYmd, $toYmd);
        $lemburByKaryawan = $this->loadLemburCountsByKaryawan($pendingIds, $fromYmd, $toYmd);
        $koreksiByKaryawan = $this->loadKoreksiCountsByKaryawan($pendingIds, $fromYmd, $toYmd);
        $alpaByKaryawan = $this->loadAlpaDaysByKaryawan($pendingIds, $fromYmd, $toYmd, $byId);

        $leaveBalance = app(LeaveBalanceService::class);

        foreach ($pendingIds as $id) {
            $employee = $byId[$id];
            $metrics = $this->emptyMetrics();

            foreach ($permissionByKaryawan[$id] ?? [] as $row) {
                $this->accumulatePermissionRow($metrics, $row, $fromYmd, $toYmd);
            }

            $metrics['lembur_count'] = (int) ($lemburByKaryawan[$id] ?? 0);
            $metrics['koreksi_kehadiran_count'] = (int) ($koreksiByKaryawan[$id] ?? 0);
            $metrics['alpa_days'] = (int) ($alpaByKaryawan[$id] ?? 0);
            $metrics['cuti_days'] = $leaveBalance->cutiDaysForRekapWindow(
                $employee,
                $windowFrom,
                $windowTo,
                $modeKey
            );

            $this->memo[$memoKey($id)] = $metrics;
        }
    }

    public function metricsForEmployee(
        MasterKaryawan $employee,
        Carbon $from,
        Carbon $to,
        string $filterMode = 'tahun'
    ): array {
        $fromYmd = $from->copy()->startOfDay()->format('Y-m-d');
        $toYmd = $to->copy()->startOfDay()->format('Y-m-d');
        $modeKey = strtolower(trim($filterMode));
        $key = (int) $employee->id . ':' . $fromYmd . ':' . $toYmd . ':' . $modeKey;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $employee = $this->resolveEmployee($employee);

        $leaveBalance = app(LeaveBalanceService::class);
        $cutiDays = $leaveBalance->cutiDaysForRekapWindow(
            $employee,
            $from->copy()->startOfDay(),
            $to->copy()->startOfDay(),
            $modeKey
        );

        $sakitDays = 0;
        $kegiatanDays = 0;
        $terlambatCount = 0;

        $permissionRows = DB::table('hr_request as hr')
            ->join('hr_permission_detail as pd', 'pd.request_id', '=', 'hr.id')
            ->where('hr.karyawan_id', $employee->id)
            ->where('hr.request_type', HrRequest::TYPE_PERMISSION)
            ->where('hr.is_active', true)
            ->where('hr.status', WorkflowStatus::APPROVED_HRD)
            ->where('pd.start_date', '<=', $toYmd)
            ->where(function ($q) use ($fromYmd) {
                $q->where('pd.end_date', '>=', $fromYmd)
                    ->orWhere(function ($q2) use ($fromYmd) {
                        $q2->whereNull('pd.end_date')
                            ->where('pd.start_date', '>=', $fromYmd);
                    });
            })
            ->select('pd.permission_kind', 'pd.start_date', 'pd.end_date')
            ->get();

        foreach ($permissionRows as $row) {
            $kind = strtolower(trim((string) ($row->permission_kind ?? '')));
            $start = $row->start_date;
            $end = $row->end_date ?: $start;

            if ($kind === 'late') {
                if ($this->dateInRange($start, $fromYmd, $toYmd)) {
                    $terlambatCount++;
                }
                continue;
            }

            $days = $this->workingDaysInOverlap($start, $end, $fromYmd, $toYmd);
            if ($kind === 'sick') {
                $sakitDays += $days;
            } else {
                $kegiatanDays += $days;
            }
        }

        $lemburCount = (int) DB::table('hr_overtime_participant as op')
            ->join('hr_request as hr', 'hr.id', '=', 'op.request_id')
            ->join('hr_overtime_detail as od', 'od.request_id', '=', 'hr.id')
            ->where('op.karyawan_id', $employee->id)
            ->where('op.is_active', true)
            ->where('hr.request_type', HrRequest::TYPE_OVERTIME)
            ->where('hr.is_active', true)
            ->whereIn('hr.status', WorkflowStatus::formsStatApprovedOvertimeStatuses())
            ->where('od.start_date', '<=', $toYmd)
            ->where('od.end_date', '>=', $fromYmd)
            ->distinct()
            ->count('hr.id');

        $koreksiKehadiranCount = (int) DB::table('hr_request as hr')
            ->join('hr_attendance_correction_detail as acd', 'acd.request_id', '=', 'hr.id')
            ->where('hr.karyawan_id', $employee->id)
            ->where('hr.request_type', HrRequest::TYPE_ATTENDANCE_CORRECTION)
            ->where('hr.is_active', true)
            ->where('hr.status', WorkflowStatus::APPROVED_HRD)
            ->whereBetween('acd.correction_date', [$fromYmd, $toYmd])
            ->distinct()
            ->count('hr.id');

        $alpaDays = 0;
        if (!LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee)) {
            $alpaDays = (int) HrLeaveBalanceLedger::query()
                ->where('karyawan_id', $employee->id)
                ->where('entry_type', HrLeaveBalanceLedger::TYPE_ALPA)
                ->where('is_void', false)
                ->where(function ($q) use ($fromYmd, $toYmd) {
                    $q->whereBetween('reference_date', [$fromYmd, $toYmd])
                        ->orWhere(function ($q2) use ($fromYmd, $toYmd) {
                            $q2->whereNull('reference_date')
                                ->whereBetween('created_at', [$fromYmd . ' 00:00:00', $toYmd . ' 23:59:59']);
                        });
                })
                ->sum('days_delta');
            $alpaDays = max(0, $alpaDays);
        }

        $this->memo[$key] = [
            'cuti_days' => $cutiDays,
            'izin_sakit_days' => $sakitDays,
            'izin_kegiatan_days' => $kegiatanDays,
            'izin_terlambat_count' => $terlambatCount,
            'lembur_count' => $lemburCount,
            'koreksi_kehadiran_count' => $koreksiKehadiranCount,
            'alpa_days' => $alpaDays,
        ];

        return $this->memo[$key];
    }

    /**
     * @return array{
     *   cuti_days: int,
     *   izin_sakit_days: int,
     *   izin_kegiatan_days: int,
     *   izin_terlambat_count: int,
     *   lembur_count: int,
     *   koreksi_kehadiran_count: int,
     *   alpa_days: int
     * }
     */
    private function emptyMetrics(): array
    {
        return [
            'cuti_days' => 0,
            'izin_sakit_days' => 0,
            'izin_kegiatan_days' => 0,
            'izin_terlambat_count' => 0,
            'lembur_count' => 0,
            'koreksi_kehadiran_count' => 0,
            'alpa_days' => 0,
        ];
    }

    private function resolveEmployee(MasterKaryawan $employee): MasterKaryawan
    {
        $attributes = $employee->getAttributes();
        if (array_key_exists('join_date', $attributes) && array_key_exists('grade', $attributes)) {
            return $employee;
        }

        return MasterKaryawan::query()->find($employee->id) ?: $employee;
    }

    /**
     * @param  list<int>  $karyawanIds
     * @return array<int, list<object>>
     */
    private function loadPermissionRowsByKaryawan(array $karyawanIds, string $fromYmd, string $toYmd): array
    {
        $rows = DB::table('hr_request as hr')
            ->join('hr_permission_detail as pd', 'pd.request_id', '=', 'hr.id')
            ->whereIn('hr.karyawan_id', $karyawanIds)
            ->where('hr.request_type', HrRequest::TYPE_PERMISSION)
            ->where('hr.is_active', true)
            ->where('hr.status', WorkflowStatus::APPROVED_HRD)
            ->where('pd.start_date', '<=', $toYmd)
            ->where(function ($q) use ($fromYmd) {
                $q->where('pd.end_date', '>=', $fromYmd)
                    ->orWhere(function ($q2) use ($fromYmd) {
                        $q2->whereNull('pd.end_date')
                            ->where('pd.start_date', '>=', $fromYmd);
                    });
            })
            ->select('hr.karyawan_id', 'pd.permission_kind', 'pd.start_date', 'pd.end_date')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row->karyawan_id;
            $grouped[$id][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $karyawanIds
     * @return array<int, int>
     */
    private function loadLemburCountsByKaryawan(array $karyawanIds, string $fromYmd, string $toYmd): array
    {
        return DB::table('hr_overtime_participant as op')
            ->join('hr_request as hr', 'hr.id', '=', 'op.request_id')
            ->join('hr_overtime_detail as od', 'od.request_id', '=', 'hr.id')
            ->whereIn('op.karyawan_id', $karyawanIds)
            ->where('op.is_active', true)
            ->where('hr.request_type', HrRequest::TYPE_OVERTIME)
            ->where('hr.is_active', true)
            ->whereIn('hr.status', WorkflowStatus::formsStatApprovedOvertimeStatuses())
            ->where('od.start_date', '<=', $toYmd)
            ->where('od.end_date', '>=', $fromYmd)
            ->groupBy('op.karyawan_id')
            ->selectRaw('op.karyawan_id, COUNT(DISTINCT hr.id) as aggregate_count')
            ->pluck('aggregate_count', 'karyawan_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /**
     * @param  list<int>  $karyawanIds
     * @return array<int, int>
     */
    private function loadKoreksiCountsByKaryawan(array $karyawanIds, string $fromYmd, string $toYmd): array
    {
        return DB::table('hr_request as hr')
            ->join('hr_attendance_correction_detail as acd', 'acd.request_id', '=', 'hr.id')
            ->whereIn('hr.karyawan_id', $karyawanIds)
            ->where('hr.request_type', HrRequest::TYPE_ATTENDANCE_CORRECTION)
            ->where('hr.is_active', true)
            ->where('hr.status', WorkflowStatus::APPROVED_HRD)
            ->whereBetween('acd.correction_date', [$fromYmd, $toYmd])
            ->groupBy('hr.karyawan_id')
            ->selectRaw('hr.karyawan_id, COUNT(DISTINCT hr.id) as aggregate_count')
            ->pluck('aggregate_count', 'karyawan_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /**
     * @param  list<int>  $karyawanIds
     * @param  array<int, MasterKaryawan>  $employeesById
     * @return array<int, int>
     */
    private function loadAlpaDaysByKaryawan(
        array $karyawanIds,
        string $fromYmd,
        string $toYmd,
        array $employeesById
    ): array {
        $eligibleIds = [];
        foreach ($karyawanIds as $id) {
            $employee = $employeesById[$id] ?? null;
            if ($employee && !LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee)) {
                $eligibleIds[] = $id;
            }
        }

        if ($eligibleIds === []) {
            return [];
        }

        return HrLeaveBalanceLedger::query()
            ->whereIn('karyawan_id', $eligibleIds)
            ->where('entry_type', HrLeaveBalanceLedger::TYPE_ALPA)
            ->where('is_void', false)
            ->where(function ($q) use ($fromYmd, $toYmd) {
                $q->whereBetween('reference_date', [$fromYmd, $toYmd])
                    ->orWhere(function ($q2) use ($fromYmd, $toYmd) {
                        $q2->whereNull('reference_date')
                            ->whereBetween('created_at', [$fromYmd . ' 00:00:00', $toYmd . ' 23:59:59']);
                    });
            })
            ->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, SUM(days_delta) as aggregate_days')
            ->pluck('aggregate_days', 'karyawan_id')
            ->map(function ($value) {
                return max(0, (int) $value);
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $metrics
     * @param  object  $row
     */
    private function accumulatePermissionRow(array &$metrics, $row, string $fromYmd, string $toYmd): void
    {
        $kind = strtolower(trim((string) ($row->permission_kind ?? '')));
        $start = $row->start_date;
        $end = $row->end_date ?: $start;

        if ($kind === 'late') {
            if ($this->dateInRange($start, $fromYmd, $toYmd)) {
                $metrics['izin_terlambat_count']++;
            }

            return;
        }

        $days = $this->workingDaysInOverlap($start, $end, $fromYmd, $toYmd);
        if ($kind === 'sick') {
            $metrics['izin_sakit_days'] += $days;
        } else {
            $metrics['izin_kegiatan_days'] += $days;
        }
    }

    /**
     * @param  string|\DateTimeInterface|null  $date
     */
    private function dateInRange($date, string $fromYmd, string $toYmd): bool
    {
        if (!$date) {
            return false;
        }

        try {
            $ymd = Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            return false;
        }

        return $ymd >= $fromYmd && $ymd <= $toYmd;
    }

    /**
     * @param  string|\DateTimeInterface|null  $start
     * @param  string|\DateTimeInterface|null  $end
     */
    private function workingDaysInOverlap($start, $end, string $filterFrom, string $filterTo): int
    {
        if (!$start) {
            return 0;
        }

        try {
            $from = Carbon::parse($start)->startOfDay();
            $to = Carbon::parse($end ?: $start)->startOfDay();
            $windowFrom = Carbon::parse($filterFrom)->startOfDay();
            $windowTo = Carbon::parse($filterTo)->startOfDay();
        } catch (\Throwable $e) {
            return 0;
        }

        if ($to->lt($from)) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        if ($from->gt($windowTo) || $to->lt($windowFrom)) {
            return 0;
        }

        $overlapFrom = $from->gt($windowFrom) ? $from : $windowFrom;
        $overlapTo = $to->lt($windowTo) ? $to : $windowTo;

        return $this->rekapLibur->countWorkingDaysBetween(
            $overlapFrom->format('Y-m-d'),
            $overlapTo->format('Y-m-d')
        );
    }

}
