<?php

namespace App\Services\Greatday;

use App\Models\Greatday\LeaveRequest;
use App\Models\Greatday\PermissionRequest;
use App\Models\Hr\HrRequest;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;

/**
 * Tanggal yang tidak boleh di-sync alpa (izin/cuti disetujui).
 */
final class LeaveAlpaExcuseCalendar
{
    /** @var array<int, list<array{0: string, 1: string}>> */
    private $rangesByKaryawan = [];

    private $reminderPermissionsByKaryawan = [];

    /** @param list<int> $karyawanIds */
    public static function forEmployees(array $karyawanIds, Carbon $rangeFrom, Carbon $rangeTo): self
    {
        $calendar = new self();
        $ids = array_values(array_unique(array_filter(array_map('intval', $karyawanIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            return $calendar;
        }

        $fromYmd = $rangeFrom->toDateString();
        $toYmd = $rangeTo->toDateString();

        if (HrTableMode::usesLegacyHrTables()) {
            $calendar->loadLegacy($ids, $fromYmd, $toYmd);
        } else {
            $calendar->loadHr($ids, $fromYmd, $toYmd, null);
        }

        return $calendar;
    }

    /**
     * Cuti/izin dari hr_request + detail di DB produksi (intilab_produksi), tanpa legacy apps.
     * Dipakai reminder absensi & alur yang sudah migrasi ke hr_*.
     *
     * @param  list<int>  $karyawanIds
     */
    public static function forProduksiHrTables(array $karyawanIds, Carbon $rangeFrom, Carbon $rangeTo): self
    {
        $calendar = new self();
        $ids = array_values(array_unique(array_filter(array_map('intval', $karyawanIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            return $calendar;
        }

        $connection = (string) config('greatday.produksi_connection', config('database.default', 'mysql'));
        $calendar->loadHr(
            $ids,
            $rangeFrom->toDateString(),
            $rangeTo->toDateString(),
            $connection
        );

        return $calendar;
    }

    /**
     * Reminder absensi: cuti/izin cukup sudah diajukan (hr_request is_active + detail tanggal),
     * tanpa wajib Approved HRD (tolak Rejected Atasan/HRD).
     *
     * @param  list<int>  $karyawanIds
     */
    public static function forProduksiAttendanceReminder(array $karyawanIds, Carbon $rangeFrom, Carbon $rangeTo): self
    {
        $calendar = new self();
        $ids = array_values(array_unique(array_filter(array_map('intval', $karyawanIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            return $calendar;
        }

        $connection = (string) config('greatday.produksi_connection', config('database.default', 'mysql'));
        $calendar->loadHrForAttendanceReminder(
            $ids,
            $rangeFrom->toDateString(),
            $rangeTo->toDateString(),
            $connection
        );

        return $calendar;
    }

    public function isExcused(int $karyawanId, string $ymd): bool
    {
        foreach ($this->rangesByKaryawan[$karyawanId] ?? [] as $range) {
            if ($ymd >= $range[0] && $ymd <= $range[1]) {
                return true;
            }
        }

        return false;
    }

    public function isReminderExcused(int $karyawanId, string $shiftDate, string $type,
        Carbon $scheduledAt, Carbon $evaluatedAt): bool
    {
        // Full-day leave belongs to the shift's start date, including overnight work.
        if ($this->isExcused($karyawanId, $shiftDate)) {
            return true;
        }
        foreach ($this->reminderPermissionsByKaryawan[$karyawanId] ?? [] as $permission) {
            if ($permission['kind'] === 'late' && $type !== 'missing_masuk') {
                continue;
            }
            $ymd = $scheduledAt->toDateString();
            $end = $permission['end_time'];
            if ($permission['kind'] === 'late') {
                if ($ymd < $permission['start_date'] || $ymd > $permission['end_date']) {
                    continue;
                }
                // Legacy late requests may only store the arrival time in start_time.
                $arrivalTime = $end;
                if ($arrivalTime === null || in_array($arrivalTime, ['00:00', '00:00:00'], true)) {
                    $arrivalTime = $permission['start_time'];
                }
                // An unspecified arrival time excuses entry only, never checkout.
                if ($arrivalTime === null || $evaluatedAt->lte(Carbon::parse($ymd . ' ' . $arrivalTime, 'Asia/Jakarta'))) {
                    return true;
                }
                continue;
            }
            $start = Carbon::parse($permission['start_date'] . ' ' . ($permission['start_time'] ?? '00:00:00'), 'Asia/Jakarta');
            $until = Carbon::parse($permission['end_date'] . ' ' . ($end ?? '23:59:59'), 'Asia/Jakarta');
            if ($until->lt($start)) {
                $until->addDay();
            }
            if ($scheduledAt->betweenIncluded($start, $until) && $evaluatedAt->lte($until)) {
                return true;
            }
        }

        return false;
    }

    private function addReminderPermission(int $karyawanId, $detail, string $fromYmd, string $toYmd): void
    {
        $start = $this->formatDate($detail->start_date);
        $end = $this->formatDate($detail->end_date ?? $detail->start_date);
        if (!$start || !$end || $end < $fromYmd || $start > $toYmd) {
            return;
        }
        $kind = (string) ($detail->permission_kind ?? '');
        $startTime = $detail->start_time ?: null;
        $endTime = $detail->end_time ?: null;
        if ($kind !== 'late' && !$startTime && !$endTime) {
            $this->addRange($karyawanId, $start, $end, $fromYmd, $toYmd);
            return;
        }
        $this->reminderPermissionsByKaryawan[$karyawanId][] = [
            'kind' => $kind, 'start_date' => $start, 'end_date' => $end,
            'start_time' => $startTime, 'end_time' => $endTime,
        ];
    }

    /** @param list<int> $ids */
    private function loadHr(array $ids, string $fromYmd, string $toYmd, ?string $connection = null): void
    {
        $statuses = [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD];

        $permissions = $this->hrRequestQuery($connection)
            ->with('permissionDetail')
            ->whereIn('karyawan_id', $ids)
            ->where('request_type', HrRequest::TYPE_PERMISSION)
            ->where('is_active', true)
            ->whereIn('status', $statuses)
            ->get();

        foreach ($permissions as $row) {
            $detail = $row->permissionDetail;
            if (!$detail) {
                continue;
            }
            $this->addRange((int) $row->karyawan_id, $this->formatDate($detail->start_date), $this->formatDate($detail->end_date), $fromYmd, $toYmd);
        }

        $leaves = $this->hrRequestQuery($connection)
            ->with('leaveDetail')
            ->whereIn('karyawan_id', $ids)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->whereIn('status', $statuses)
            ->get();

        foreach ($leaves as $row) {
            $detail = $row->leaveDetail;
            if (!$detail) {
                continue;
            }
            if (($detail->leave_kind ?? '') === 'phl') {
                $replacement = $this->formatDate($detail->end_date);
                $this->addRange((int) $row->karyawan_id, $replacement, $replacement, $fromYmd, $toYmd);
            } else {
                $this->addRange((int) $row->karyawan_id, $this->formatDate($detail->start_date), $this->formatDate($detail->end_date), $fromYmd, $toYmd);
            }
        }
    }

    /** @param list<int> $ids */
    private function loadHrForAttendanceReminder(array $ids, string $fromYmd, string $toYmd, ?string $connection): void
    {
        $rejected = [WorkflowStatus::REJECTED_ATASAN, WorkflowStatus::REJECTED_HRD];

        $submittedActive = fn () => $this->hrRequestQuery($connection)
            ->whereIn('karyawan_id', $ids)
            ->where('is_active', 1)
            ->whereNotNull('submitted_at')
            ->whereNotIn('status', $rejected);

        $permissions = $submittedActive()
            ->with('permissionDetail')
            ->where('request_type', HrRequest::TYPE_PERMISSION)
            ->get();

        foreach ($permissions as $row) {
            $detail = $row->permissionDetail;
            if (!$detail) {
                continue;
            }
            $this->addReminderPermission((int) $row->karyawan_id, $detail, $fromYmd, $toYmd);
        }

        $leaves = $submittedActive()
            ->with('leaveDetail')
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->get();

        foreach ($leaves as $row) {
            $detail = $row->leaveDetail;
            if (!$detail) {
                continue;
            }
            if (($detail->leave_kind ?? '') === 'phl') {
                $replacement = $this->formatDate($detail->end_date);
                $this->addRange((int) $row->karyawan_id, $replacement, $replacement, $fromYmd, $toYmd);
            } else {
                $this->addRange((int) $row->karyawan_id, $this->formatDate($detail->start_date), $this->formatDate($detail->end_date), $fromYmd, $toYmd);
            }
        }
    }

    /** @param list<int> $ids */
    private function loadLegacy(array $ids, string $fromYmd, string $toYmd): void
    {
        $statuses = [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD];

        $permissions = PermissionRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('is_active', true)
            ->whereIn('status', $statuses)
            ->get();

        foreach ($permissions as $row) {
            $this->addRange((int) $row->employee_id, $this->formatDate($row->start_date ?? null), $this->formatDate($row->end_date ?? null), $fromYmd, $toYmd);
        }

        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('is_active', true)
            ->whereIn('status', $statuses)
            ->get();

        foreach ($leaves as $row) {
            if ($row->type === 'Holiday Replacement Leave') {
                $replacement = $this->formatDate($row->end_date ?? null);
                $this->addRange((int) $row->employee_id, $replacement, $replacement, $fromYmd, $toYmd);
            } else {
                $this->addRange((int) $row->employee_id, $this->formatDate($row->start_date ?? null), $this->formatDate($row->end_date ?? null), $fromYmd, $toYmd);
            }
        }
    }

    /** @return \Illuminate\Database\Eloquent\Builder<HrRequest> */
    private function hrRequestQuery(?string $connection)
    {
        if ($connection !== null && $connection !== '') {
            return HrRequest::on($connection);
        }

        return HrRequest::query();
    }

    private function addRange(int $karyawanId, ?string $start, ?string $end, string $clipFrom, string $clipTo): void
    {
        if ($start === null || $end === null || $start === '' || $end === '') {
            return;
        }
        if ($end < $clipFrom || $start > $clipTo) {
            return;
        }

        $effectiveFrom = $start < $clipFrom ? $clipFrom : $start;
        $effectiveTo = $end > $clipTo ? $clipTo : $end;

        $this->rangesByKaryawan[$karyawanId][] = [$effectiveFrom, $effectiveTo];
    }

    /** @param mixed $value */
    private function formatDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}