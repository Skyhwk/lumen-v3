<?php

namespace App\Services\Hr\Greatday;

use App\Models\MasterShiftKaryawan;
use App\Models\ShiftKaryawan;
use App\Services\Hr\MonthlyAbsensiDataBuilder;
use App\Services\Hr\ShiftAbsensiPunchRules;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Terapkan koreksi absensi Approved HRD ke tabel produksi `absensi` (shift-aware).
 */
class AttendanceCorrectionAbsensiApplyService
{
    public function isAlreadyApplied(int $employeeId, string $correctionDate, string $type, string $time): bool
    {
        $context = $this->buildApplyContext($employeeId, $correctionDate, $type, $time);
        if ($context === null) {
            return false;
        }

        return $this->shouldSkipApply($context);
    }

    /**
     * @return bool true jika absensi ditulis/diupdate, false jika dilewati (sudah sesuai)
     */
    public function apply(int $employeeId, string $correctionDate, string $type, string $time): bool
    {
        $context = $this->buildApplyContext($employeeId, $correctionDate, $type, $time);
        if ($context === null) {
            return false;
        }

        if ($this->shouldSkipApply($context)) {
            return false;
        }

        $conn = $context['conn'];
        $absensiDate = $context['absensiDate'];
        $time = $context['time'];
        $status = $context['status'];
        $existing = $context['existingSlot'];

        if ($existing !== null) {

            DB::connection($conn)->table('absensi')->where('id', $existing->id)->update([
                'tanggal' => $absensiDate,
                'hari' => MonthlyAbsensiDataBuilder::hariIndonesia($absensiDate),
                'jam' => $time,
                'status' => $status,
                'kode_kartu' => null,
            ]);

            return true;
        }

        DB::connection($conn)->table('absensi')->insert([
            'karyawan_id' => $employeeId,
            'hari' => MonthlyAbsensiDataBuilder::hariIndonesia($absensiDate),
            'tanggal' => $absensiDate,
            'jam' => $time,
            'status' => $status,
            'kode_kartu' => null,
            'kode_mesin' => null,
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildApplyContext(int $employeeId, string $correctionDate, string $type, string $time): ?array
    {
        $time = $this->normalizeTime($time);
        if ($time === '') {
            return null;
        }

        $conn = (string) config('greatday.produksi_connection', config('database.default', 'mysql'));
        $isCheckIn = $this->isCheckInType($type);

        $shiftRowOnDate = $this->shiftRow($conn, $employeeId, $correctionDate);
        $shiftOnDate = $shiftRowOnDate ? (string) ($shiftRowOnDate->shift ?? '') : null;
        $prevDate = Carbon::parse($correctionDate)->subDay()->toDateString();
        $shiftRowPrevDate = $this->shiftRow($conn, $employeeId, $prevDate);
        $shiftPrevDate = $shiftRowPrevDate ? (string) ($shiftRowPrevDate->shift ?? '') : null;

        $absensiDate = self::resolveStorageDate(
            $correctionDate,
            $time,
            $isCheckIn,
            $shiftOnDate,
            $shiftPrevDate,
            $shiftRowOnDate,
            $shiftRowPrevDate,
            $conn
        );
        $status = $isCheckIn ? 'Masuk' : 'Keluar';

        $existingSlot = $this->findExistingPunchRow(
            $conn,
            $employeeId,
            $correctionDate,
            $absensiDate,
            $isCheckIn,
            $shiftOnDate,
            $shiftPrevDate,
            $shiftRowOnDate,
            $shiftRowPrevDate
        );

        return [
            'conn' => $conn,
            'employeeId' => $employeeId,
            'absensiDate' => $absensiDate,
            'time' => $time,
            'status' => $status,
            'existingSlot' => $existingSlot,
        ];
    }

    /** @param array<string, mixed> $context */
    private function shouldSkipApply(array $context): bool
    {
        $conn = $context['conn'];
        $employeeId = (int) $context['employeeId'];
        $absensiDate = $context['absensiDate'];
        $time = $context['time'];
        $status = $context['status'];
        $existing = $context['existingSlot'];

        if ($this->hasExactPunch($conn, $employeeId, $absensiDate, $time, $status)) {
            return true;
        }

        if ($existing !== null && $this->rowAlreadyMatches($existing, $absensiDate, $time, $status)) {
            return true;
        }

        return false;
    }

    /**
     * Tanggal kalender baris di `absensi` (bukan selalu sama dengan tanggal koreksi).
     */
    public static function resolveStorageDate(
        string $correctionDate,
        string $time,
        bool $isCheckIn,
        ?string $shiftOnDate,
        ?string $shiftPreviousDate,
        ?object $shiftRowOnDate = null,
        ?object $shiftRowPreviousDate = null,
        ?string $connection = null
    ): string {
        if ($isCheckIn) {
            return $correctionDate;
        }

        if (self::isOvernightShift($shiftPreviousDate, $shiftRowPreviousDate, $connection)) {
            $boundsPrev = MasterShiftKaryawan::resolveNightShiftBounds(
                $shiftPreviousDate,
                $shiftRowPreviousDate->time_in ?? null,
                $shiftRowPreviousDate->time_out ?? null,
                $connection
            );
            $prevCutoff = MasterShiftKaryawan::is24Jam($shiftPreviousDate)
                ? ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE
                : $boundsPrev['keluar_before'];
            if ($time < $prevCutoff) {
                return $correctionDate;
            }
        }

        if (self::isOvernightShift($shiftOnDate, $shiftRowOnDate, $connection)) {
            $boundsOn = MasterShiftKaryawan::resolveNightShiftBounds(
                $shiftOnDate,
                $shiftRowOnDate->time_in ?? null,
                $shiftRowOnDate->time_out ?? null,
                $connection
            );
            $onCutoff = MasterShiftKaryawan::is24Jam($shiftOnDate)
                ? ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE
                : $boundsOn['keluar_before'];
            if ($time < $onCutoff) {
                return Carbon::parse($correctionDate)->addDay()->toDateString();
            }
        }

        return $correctionDate;
    }

    private function hasExactPunch(string $conn, int $employeeId, string $absensiDate, string $time, string $status): bool
    {
        foreach ($this->rowsOnDate($conn, $employeeId, $absensiDate) as $row) {
            if ($this->normalizeTime((string) $row->jam) !== $time) {
                continue;
            }
            if (strcasecmp((string) ($row->status ?? ''), $status) === 0) {
                return true;
            }
        }

        return false;
    }

    private function rowAlreadyMatches(object $row, string $absensiDate, string $time, string $status): bool
    {
        $rowDate = Carbon::parse((string) $row->tanggal)->toDateString();

        return $rowDate === $absensiDate
            && $this->normalizeTime((string) $row->jam) === $time
            && strcasecmp((string) ($row->status ?? ''), $status) === 0;
    }

    private function findExistingPunchRow(
        string $conn,
        int $employeeId,
        string $correctionDate,
        string $absensiDate,
        bool $isCheckIn,
        ?string $shiftOnDate,
        ?string $shiftPrevDate,
        ?object $shiftRowOnDate = null,
        ?object $shiftRowPrevDate = null
    ): ?object {
        if ($isCheckIn) {
            return $this->findCheckInRow($conn, $employeeId, $correctionDate, $shiftOnDate, $shiftRowOnDate);
        }

        return $this->findCheckOutRow($conn, $employeeId, $absensiDate, $shiftOnDate, $shiftPrevDate, $shiftRowOnDate, $shiftRowPrevDate);
    }

    private function findCheckInRow(
        string $conn,
        int $employeeId,
        string $correctionDate,
        ?string $shiftOnDate,
        ?object $shiftRowOnDate = null
    ): ?object {
        $rows = $this->rowsOnDate($conn, $employeeId, $correctionDate);
        if ($rows->isEmpty()) {
            return null;
        }

        $timeIn = $shiftRowOnDate->time_in ?? null;
        $timeOut = $shiftRowOnDate->time_out ?? null;

        if (MasterShiftKaryawan::isNightShift($shiftOnDate, $timeIn, $timeOut, $conn)) {
            return ShiftAbsensiPunchRules::pickNightShiftMasukRow($rows, $shiftOnDate, $timeIn, $timeOut, $conn);
        }
        if (MasterShiftKaryawan::is24Jam($shiftOnDate)) {
            $minJam = $rows->min('jam');

            return $minJam !== null && (string) $minJam > '00:00:00'
                ? $rows->first(fn ($row) => (string) $row->jam === (string) $minJam)
                : null;
        }

        // Shift siang (jam masuk >= 10:00 seperti SHTEKNISI/SHOB2/SHKORSAMP2): pilih punch paling awal
        $resolvedInHm = MasterShiftKaryawan::normalizeHm($timeIn)
            ?? ($shiftOnDate ? MasterShiftKaryawan::getShiftTimes($shiftOnDate, $conn)['in'] : null);
        if ($resolvedInHm !== null && $resolvedInHm >= '10:00') {
            return $rows->sortBy('jam')->first();
        }

        $morning = $rows->filter(
            fn ($row) => (string) $row->jam <= ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE
        );

        return $morning->sortBy('jam')->first() ?? $rows->sortBy('jam')->first();
    }

    private function findCheckOutRow(
        string $conn,
        int $employeeId,
        string $absensiDate,
        ?string $shiftOnDate,
        ?string $shiftPrevDate,
        ?object $shiftRowOnDate = null,
        ?object $shiftRowPrevDate = null
    ): ?object {
        $rows = $this->rowsOnDate($conn, $employeeId, $absensiDate);
        if ($rows->isEmpty()) {
            return null;
        }

        if (self::isOvernightShift($shiftOnDate, $shiftRowOnDate, $conn) || self::isOvernightShift($shiftPrevDate, $shiftRowPrevDate, $conn)) {
            $refCode = self::isOvernightShift($shiftPrevDate, $shiftRowPrevDate, $conn) ? $shiftPrevDate : $shiftOnDate;
            $refRow = self::isOvernightShift($shiftPrevDate, $shiftRowPrevDate, $conn) ? $shiftRowPrevDate : $shiftRowOnDate;
            $cutoff = MasterShiftKaryawan::is24Jam($refCode)
                ? ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE
                : MasterShiftKaryawan::resolveNightShiftBounds($refCode, $refRow->time_in ?? null, $refRow->time_out ?? null, $conn)['keluar_before'];

            return ShiftAbsensiPunchRules::pickOvernightKeluarRow($rows, $cutoff);
        }

        $afternoon = $rows->filter(
            fn ($row) => (string) $row->jam > ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE
        );

        if ($afternoon->isNotEmpty()) {
            $maxJam = $afternoon->max('jam');

            return $afternoon->first(fn ($row) => (string) $row->jam === (string) $maxJam);
        }

        return $rows->sortByDesc('jam')->first();
    }

    /** @return Collection<int, object> */
    private function rowsOnDate(string $conn, int $employeeId, string $date): Collection
    {
        return DB::connection($conn)->table('absensi')
            ->where('karyawan_id', $employeeId)
            ->where('tanggal', $date)
            ->orderBy('jam')
            ->get(['id', 'karyawan_id', 'tanggal', 'jam', 'kode_mesin', 'status']);
    }

    private function shiftRow(string $conn, int $employeeId, string $date): ?object
    {
        return ShiftKaryawan::on($conn)
            ->where('karyawan_id', $employeeId)
            ->where('tanggal', $date)
            ->first(['shift', 'time_in', 'time_out']);
    }

    private function isCheckInType(string $type): bool
    {
        $normalized = strtolower(preg_replace('/[\s_-]+/', '', trim($type)));

        return $normalized === 'checkin';
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return $time;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }

    private static function isOvernightShift(?string $shift, ?object $shiftRow = null, ?string $connection = null): bool
    {
        return MasterShiftKaryawan::isOvernight(
            $shift,
            $shiftRow->time_in ?? null,
            $shiftRow->time_out ?? null,
            $connection
        );
    }
}
