<?php

namespace App\Services\Payroll;

use App\Models\Greatday\LeaveRequest;
use App\Models\Hr\HrRequest;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;

/**
 * Karyawan Training: hari cuti khusus (disetujui HRD) tidak dihitung potongan absen.
 */
final class PayrollTrainingSpecialLeaveService
{
    /**
     * @param  list<int>  $karyawanIds
     * @return array<int, int> karyawan_id => jumlah hari cuti khusus dalam bulan periode
     */
    public static function mapApprovedSpecialLeaveDayCounts(array $karyawanIds, string $periodeYm): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $karyawanIds), fn ($id) => $id > 0)));
        if ($ids === [] || !preg_match('/^\d{4}-\d{2}$/', $periodeYm)) {
            return [];
        }

        $parts = explode('-', $periodeYm);
        $monthStart = sprintf('%s-%s-01', $parts[0], $parts[1]);
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        /** @var array<int, array<string, true>> $datesByKaryawan */
        $datesByKaryawan = [];

        if (HrTableMode::usesLegacyHrTables()) {
            self::collectLegacySpecialLeaveDates($ids, $monthStart, $monthEnd, $datesByKaryawan);
        } else {
            self::collectHrSpecialLeaveDates($ids, $monthStart, $monthEnd, $datesByKaryawan);
        }

        $counts = [];
        foreach ($datesByKaryawan as $karyawanId => $dates) {
            $counts[$karyawanId] = count($dates);
        }

        return $counts;
    }

    /**
     * Sesuaikan preview payroll (belum submit): kurangi tidak hadir & potongan absen.
     */
    public static function applyToPreviewRow(object $row, int $specialLeaveDays): void
    {
        if ($specialLeaveDays <= 0 || (string) ($row->status_karyawan ?? '') !== 'Training') {
            return;
        }
        if (!empty($row->payroll_id)) {
            return;
        }

        $hariKerja = (float) ($row->hari_kerja ?? 0);
        $tidakHadir = (float) ($row->tidak_hadir ?? 0);
        if ($hariKerja <= 0 || $tidakHadir <= 0) {
            return;
        }

        $excuse = min($specialLeaveDays, $tidakHadir);
        $newTidakHadir = $tidakHadir - $excuse;
        if ($newTidakHadir <= 0) {
            $newPotongan = 0.0;
        } else {
            $newPotongan = self::calcPotonganAbsen(
                (float) ($row->gaji_pokok ?? 0),
                (float) ($row->tunjangan_kerja ?? 0),
                $hariKerja,
                $newTidakHadir
            );
        }

        $oldPotongan = (float) ($row->potongan_absen ?? 0);
        $row->tidak_hadir = $newTidakHadir;
        $row->potongan_absen = $newPotongan;
        $row->take_home_pay = round((float) ($row->take_home_pay ?? 0) + ($oldPotongan - $newPotongan), 0);
        $row->cuti_khusus_tidak_dipotong = $excuse;
    }

    public static function calcPotonganAbsen(float $gajiPokok, float $tunjangan, float $hariKerja, float $tidakHadir): float
    {
        if ($hariKerja <= 0 || $tidakHadir <= 0) {
            return 0.0;
        }

        $daily = ($gajiPokok + $tunjangan) / $hariKerja * $tidakHadir;

        return (float) (floor(max(1000, round($daily, 0)) / 1000) * 1000);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, array<string, true>>  $datesByKaryawan
     */
    private static function collectHrSpecialLeaveDates(
        array $ids,
        string $monthStart,
        string $monthEnd,
        array &$datesByKaryawan
    ): void {
        $leaves = HrRequest::query()
            ->with('leaveDetail')
            ->whereIn('karyawan_id', $ids)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->where('status', WorkflowStatus::APPROVED_HRD)
            ->get();

        foreach ($leaves as $request) {
            $detail = $request->leaveDetail;
            if (!$detail || (string) ($detail->leave_kind ?? '') !== 'special') {
                continue;
            }
            $karyawanId = (int) $request->karyawan_id;
            self::addDatesInRange(
                $datesByKaryawan,
                $karyawanId,
                self::formatYmd($detail->start_date ?? null),
                self::formatYmd($detail->end_date ?? null),
                $monthStart,
                $monthEnd
            );
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, array<string, true>>  $datesByKaryawan
     */
    private static function collectLegacySpecialLeaveDates(
        array $ids,
        string $monthStart,
        string $monthEnd,
        array &$datesByKaryawan
    ): void {
        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('is_active', true)
            ->where('status', WorkflowStatus::APPROVED_HRD)
            ->where('type', 'Special Leave')
            ->get();

        foreach ($leaves as $row) {
            $karyawanId = (int) $row->employee_id;
            self::addDatesInRange(
                $datesByKaryawan,
                $karyawanId,
                self::formatYmd($row->start_date ?? null),
                self::formatYmd($row->end_date ?? null),
                $monthStart,
                $monthEnd
            );
        }
    }

    /**
     * @param  array<int, array<string, true>>  $datesByKaryawan
     */
    private static function addDatesInRange(
        array &$datesByKaryawan,
        int $karyawanId,
        ?string $start,
        ?string $end,
        string $monthStart,
        string $monthEnd
    ): void {
        if ($start === null || $end === null || $start === '' || $end === '') {
            return;
        }
        if ($end < $monthStart || $start > $monthEnd) {
            return;
        }

        $from = $start < $monthStart ? $monthStart : $start;
        $to = $end > $monthEnd ? $monthEnd : $end;

        try {
            $cursor = Carbon::parse($from)->startOfDay();
            $last = Carbon::parse($to)->startOfDay();
        } catch (\Throwable $e) {
            return;
        }

        while ($cursor->lte($last)) {
            $datesByKaryawan[$karyawanId][$cursor->toDateString()] = true;
            $cursor->addDay();
        }
    }

    /** @param mixed $value */
    private static function formatYmd($value): ?string
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
