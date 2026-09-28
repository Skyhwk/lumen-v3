<?php

namespace App\Services\Hr;

use App\Models\Greatday\LeaveRequest;
use App\Models\Greatday\PermissionRequest;
use App\Models\Hr\HrRequest;
use App\Models\Hr\HrSpecialLeaveType;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;

/**
 * Label cuti/izin per karyawan & tanggal untuk layar absensi portal HRD.
 */
final class HrAttendanceDayExcuseLabels
{
    /** @var array<int, array<string, list<string>>> */
    private $labelsByKaryawanDate = [];

    /**
     * @param list<int> $karyawanIds
     * @return array<int, array<string, string>> [karyawan_id => [Y-m-d => label]]
     */
    public static function mapForKaryawans(array $karyawanIds, string $fromYmd, string $toYmd): array
    {
        $instance = new self();
        $ids = array_values(array_unique(array_filter(array_map('intval', $karyawanIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        if (HrTableMode::usesLegacyHrTables()) {
            $instance->loadLegacy($ids, $fromYmd, $toYmd);
        } else {
            $instance->loadHr($ids, $fromYmd, $toYmd);
        }

        return $instance->flatten();
    }

    /**
     * @return array<string, string> [Y-m-d => label]
     */
    public static function forSingleKaryawan(int $karyawanId, string $fromYmd, string $toYmd): array
    {
        $map = self::mapForKaryawans([$karyawanId], $fromYmd, $toYmd);

        return $map[$karyawanId] ?? [];
    }

    public static function attach(array $row, int $karyawanId, array $labelMap): array
    {
        $tanggal = isset($row['tanggal']) ? date('Y-m-d', strtotime((string) $row['tanggal'])) : '';
        $label = ($labelMap[$karyawanId][$tanggal] ?? '') ?: ($labelMap[$karyawanId][$row['tanggal'] ?? ''] ?? '');
        $row['keterangan'] = $label;
        $row['hr_excused'] = $label !== '';

        return $row;
    }

    /** @return array<int, array<string, string>> */
    private function flatten(): array
    {
        $out = [];
        foreach ($this->labelsByKaryawanDate as $karyawanId => $byDate) {
            foreach ($byDate as $date => $parts) {
                $unique = array_values(array_unique(array_filter($parts, fn ($p) => $p !== '')));
                if ($unique !== []) {
                    $out[$karyawanId][$date] = implode(' / ', $unique);
                }
            }
        }

        return $out;
    }

    private function push(int $karyawanId, string $ymd, string $label): void
    {
        if ($label === '') {
            return;
        }
        $this->labelsByKaryawanDate[$karyawanId][$ymd][] = $label;
    }

    /** @param list<int> $ids */
    private function loadHr(array $ids, string $fromYmd, string $toYmd): void
    {
        $statuses = [WorkflowStatus::APPROVED_ATASAN, WorkflowStatus::APPROVED_HRD];
        $specialNames = HrSpecialLeaveType::query()->pluck('name', 'id');

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
            $label = $this->permissionLabelHr($detail, (string) $row->status);
            $this->expandRange((int) $row->karyawan_id, $this->formatDate($detail->start_date), $this->formatDate($detail->end_date), $fromYmd, $toYmd, $label);
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
            $kind = (string) ($detail->leave_kind ?? '');
            $statusSuffix = $this->statusSuffix((string) $row->status);

            if ($kind === 'phl') {
                $replacement = $this->formatDate($detail->end_date);
                $workDay = $this->formatDate($detail->start_date);
                $this->expandSingleDay((int) $row->karyawan_id, $replacement, $fromYmd, $toYmd, 'Pengganti Hari Libur' . $statusSuffix);
                if ($workDay !== null && $workDay !== $replacement) {
                    $this->expandSingleDay((int) $row->karyawan_id, $workDay, $fromYmd, $toYmd, 'Masuk pengganti hari libur' . $statusSuffix);
                }
                continue;
            }

            $label = $this->leaveLabelHr($detail, $specialNames) . $statusSuffix;
            $this->expandRange((int) $row->karyawan_id, $this->formatDate($detail->start_date), $this->formatDate($detail->end_date), $fromYmd, $toYmd, $label);
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
            $label = $this->permissionLabelLegacy($row) . $this->statusSuffix((string) $row->status);
            $this->expandRange((int) $row->employee_id, $this->formatDate($row->start_date ?? null), $this->formatDate($row->end_date ?? null), $fromYmd, $toYmd, $label);
        }

        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('is_active', true)
            ->whereIn('status', $statuses)
            ->get();

        foreach ($leaves as $row) {
            if ($row->type === 'Holiday Replacement Leave') {
                $replacement = $this->formatDate($row->end_date ?? null);
                $workDay = $this->formatDate($row->start_date ?? null);
                $suffix = $this->statusSuffix((string) $row->status);
                $this->expandSingleDay((int) $row->employee_id, $replacement, $fromYmd, $toYmd, 'Pengganti Hari Libur' . $suffix);
                if ($workDay !== null && $workDay !== $replacement) {
                    $this->expandSingleDay((int) $row->employee_id, $workDay, $fromYmd, $toYmd, 'Masuk pengganti hari libur' . $suffix);
                }
                continue;
            }

            $label = $this->leaveLabelLegacy($row) . $this->statusSuffix((string) $row->status);
            $this->expandRange((int) $row->employee_id, $this->formatDate($row->start_date ?? null), $this->formatDate($row->end_date ?? null), $fromYmd, $toYmd, $label);
        }
    }

    private function expandRange(int $karyawanId, ?string $start, ?string $end, string $clipFrom, string $clipTo, string $label): void
    {
        if ($start === null || $end === null || $start === '' || $end === '') {
            return;
        }
        if ($end < $clipFrom || $start > $clipTo) {
            return;
        }

        $effectiveFrom = $start < $clipFrom ? $clipFrom : $start;
        $effectiveTo = $end > $clipTo ? $clipTo : $end;

        try {
            $cursor = Carbon::parse($effectiveFrom);
            $last = Carbon::parse($effectiveTo);
        } catch (\Throwable $e) {
            return;
        }

        while ($cursor->lte($last)) {
            $this->push($karyawanId, $cursor->toDateString(), $label);
            $cursor->addDay();
        }
    }

    private function expandSingleDay(int $karyawanId, ?string $ymd, string $clipFrom, string $clipTo, string $label): void
    {
        if ($ymd === null || $ymd === '') {
            return;
        }
        if ($ymd < $clipFrom || $ymd > $clipTo) {
            return;
        }
        $this->push($karyawanId, $ymd, $label);
    }

    private function statusSuffix(string $status): string
    {
        if ($status === WorkflowStatus::APPROVED_ATASAN) {
            return ' (Menunggu HRD)';
        }

        return '';
    }

    /** @param \Illuminate\Support\Collection<int, string> $specialNames */
    private function leaveLabelHr($detail, $specialNames): string
    {
        $kind = (string) ($detail->leave_kind ?? '');
        if ($kind === 'special') {
            $name = $specialNames[(int) ($detail->special_leave_type_id ?? 0)] ?? null;

            return $name ? 'Cuti Khusus: ' . $name : 'Cuti Khusus';
        }
        if ($kind === 'unpaid') {
            return 'Cuti Tidak Dibayar';
        }

        return 'Cuti';
    }

    private function leaveLabelLegacy($row): string
    {
        $type = (string) ($row->type ?? '');
        if ($type === 'Special Leave') {
            return 'Cuti Khusus';
        }
        if ($type === 'Unpaid Leave') {
            return 'Cuti Tidak Dibayar';
        }

        return 'Cuti';
    }

    private function permissionLabelHr($detail, string $status): string
    {
        $kind = (string) ($detail->permission_kind ?? '');
        $timePart = $this->formatTimeRange($detail->start_time ?? null, $detail->end_time ?? null, $kind);
        $suffix = $this->statusSuffix($status);

        if ($kind === 'sick') {
            return 'Sakit' . $suffix;
        }
        if ($kind === 'late') {
            $base = $timePart !== '' ? 'Datang Terlambat ' . $timePart : 'Datang Terlambat';

            return $base . $suffix;
        }

        $base = 'Izin Kegiatan';
        if ($timePart !== '') {
            return $base . ' ' . $timePart . $suffix;
        }

        return $base . $suffix;
    }

    private function permissionLabelLegacy($row): string
    {
        $type = (string) ($row->type ?? '');
        $timePart = $this->formatTimeRange($row->start_time ?? null, $row->end_time ?? null, $type === 'Late Arrival' ? 'late' : '');

        if ($type === 'Sick Leave') {
            return 'Sakit';
        }
        if ($type === 'Late Arrival') {
            return $timePart !== '' ? 'Datang Terlambat ' . $timePart : 'Datang Terlambat';
        }

        return $timePart !== '' ? 'Izin Kegiatan ' . $timePart : 'Izin Kegiatan';
    }

    private function formatTimeRange($start, $end, string $kind): string
    {
        $a = $this->formatTimeHm($start);
        $b = $this->formatTimeHm($end);
        if ($kind === 'late') {
            return $a ?: $b;
        }
        if ($a && $b) {
            return $a . '–' . $b;
        }

        return $a ?: $b;
    }

    /** @param mixed $value */
    private function formatTimeHm($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $str = (string) $value;

        return substr($str, 0, 5);
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

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
