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
            $calendar->loadHr($ids, $fromYmd, $toYmd);
        }

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

    /** @param list<int> $ids */
    private function loadHr(array $ids, string $fromYmd, string $toYmd): void
    {
        $statuses = [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD];

        $permissions = HrRequest::query()
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

        $leaves = HrRequest::query()
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
