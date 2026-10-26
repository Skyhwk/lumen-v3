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
     *   alpa_days: int
     * }
     */
    public function metricsForEmployee(
        MasterKaryawan $employee,
        Carbon $from,
        Carbon $to,
        string $filterMode = 'tahun'
    ): array {
        $employee = MasterKaryawan::query()->find($employee->id) ?: $employee;
        $fromYmd = $from->copy()->startOfDay()->format('Y-m-d');
        $toYmd = $to->copy()->startOfDay()->format('Y-m-d');
        $modeKey = strtolower(trim($filterMode));
        $key = (int) $employee->id . ':' . $fromYmd . ':' . $toYmd . ':' . $modeKey;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

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
            'alpa_days' => $alpaDays,
        ];

        return $this->memo[$key];
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
