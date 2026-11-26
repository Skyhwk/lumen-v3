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
        $duplicateIds = $context['duplicateIds'];

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
            $shiftPrevDate
        );
        $status = $isCheckIn ? 'Masuk' : 'Keluar';

        $rows = $this->rowsOnDate($conn, $employeeId, $absensiDate);
        $existingSlot = self::resolveTargetRow(
            $rows,
            $correctionDate,
            $absensiDate,
            $time,
            $isCheckIn,
            $shiftOnDate,
            $shiftPrevDate,
            $shiftRowOnDate
        );

        return [
            'conn' => $conn,
            'employeeId' => $employeeId,
            'absensiDate' => $absensiDate,
            'time' => $time,
            'status' => $status,
            'existingSlot' => $slot['primary'],
            'duplicateIds' => $slot['duplicateIds'],
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
        $duplicateIds = $context['duplicateIds'];

        if ($existing !== null) {
            return $duplicateIds === []
                && $this->rowAlreadyMatches($existing, $absensiDate, $time, $status);
        }

        return $this->hasExactPunch($conn, $employeeId, $absensiDate, $time, $status);
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

    /**
     * Pilih satu baris target yang harus di-UPDATE tanpa pernah menyentuh baris milik pasangan slot lawannya (Masuk vs Keluar).
     * Mengembalikan null jika slot belum ada sehingga caller melakukan INSERT.
     *
     * @param  Collection<int, object>  $rows
     */
    public static function resolveTargetRow(
        Collection $rows,
        string $correctionDate,
        string $absensiDate,
        string $time,
        bool $isCheckIn,
        ?string $shiftOnDate,
        ?string $shiftPrevDate,
        ?object $shiftRowOnDate = null
    ): ?object {
        if ($rows->isEmpty()) {
            return null;
        }

        $targetStatus = $isCheckIn ? 'Masuk' : 'Keluar';

        if ($isCheckIn) {
            return self::resolveCheckInRow(
                $rows,
                $absensiDate,
                $time,
                $targetStatus,
                $shiftOnDate,
                $shiftPrevDate,
                $shiftRowOnDate
            );
        }

        return self::resolveCheckOutRow(
            $rows,
            $correctionDate,
            $absensiDate,
            $time,
            $targetStatus,
            $shiftOnDate,
            $shiftPrevDate,
            $shiftRowOnDate
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private static function resolveCheckInRow(
        Collection $rows,
        string $absensiDate,
        string $time,
        string $targetStatus,
        ?string $shiftOnDate,
        ?string $shiftPrevDate,
        ?object $shiftRowOnDate
    ): ?object {
        $shiftNorm = strtolower(trim((string) $shiftOnDate));

        if ($shiftNorm === 'shsecurity2') {
            $bucket = $rows->filter(function ($row) {
                $jam = self::normalizeJamForCompare((string) ($row->jam ?? ''));

                return $jam !== '' && $jam > ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE;
            })->values();

            if ($bucket->isEmpty()) {
                return null;
            }

            $preferred = ShiftAbsensiPunchRules::pickSecurity2MasukRow($bucket);

            return self::pickExactOrPreferred($bucket, $preferred, $absensiDate, $time, $targetStatus);
        }

        $prevIsOvernight = self::isOvernightShift($shiftPrevDate);

        if ($shiftNorm === '24jam') {
            $bucket = $rows->filter(function ($row) use ($prevIsOvernight) {
                $jam = self::normalizeJamForCompare((string) ($row->jam ?? ''));
                if ($jam === '' || $jam <= '00:00:00') {
                    return false;
                }
                if (
                    $prevIsOvernight
                    && strcasecmp((string) ($row->status ?? ''), 'Keluar') === 0
                    && $jam < ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE
                ) {
                    return false;
                }

                return true;
            })->values();

            if ($bucket->isEmpty()) {
                return null;
            }

            $preferred = $bucket->sortBy(fn ($row) => self::normalizeJamForCompare((string) ($row->jam ?? '')))->first();

            return self::pickExactOrPreferred($bucket, $preferred, $absensiDate, $time, $targetStatus);
        }

        $threshold = self::resolveDaySplitThreshold($shiftRowOnDate);
        $bucket = $rows->filter(function ($row) use ($threshold, $prevIsOvernight) {
            $jam = self::normalizeJamForCompare((string) ($row->jam ?? ''));
            if ($jam === '' || $jam > $threshold) {
                return false;
            }
            if (
                $prevIsOvernight
                && strcasecmp((string) ($row->status ?? ''), 'Keluar') === 0
                && $jam < ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE
            ) {
                return false;
            }

            return true;
        })->values();

        if ($bucket->isEmpty()) {
            return null;
        }

        $preferred = $bucket->sortBy(fn ($row) => self::normalizeJamForCompare((string) ($row->jam ?? '')))->first();

        return self::pickExactOrPreferred($bucket, $preferred, $absensiDate, $time, $targetStatus);
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private static function resolveCheckOutRow(
        Collection $rows,
        string $correctionDate,
        string $absensiDate,
        string $time,
        string $targetStatus,
        ?string $shiftOnDate,
        ?string $shiftPrevDate,
        ?object $shiftRowOnDate
    ): ?object {
        if (self::isOvernightCheckOutTarget($correctionDate, $absensiDate, $time, $shiftOnDate, $shiftPrevDate)) {
            $bucket = $rows->filter(function ($row) {
                $jam = self::normalizeJamForCompare((string) ($row->jam ?? ''));

                return $jam !== '' && $jam < ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE;
            })->values();

            if ($bucket->isEmpty()) {
                return null;
            }

            $preferred = ShiftAbsensiPunchRules::pickOvernightKeluarRow($bucket);

            return self::pickExactOrPreferred($bucket, $preferred, $absensiDate, $time, $targetStatus);
        }

        $threshold = self::resolveDaySplitThreshold($shiftRowOnDate);
        $bucket = $rows->filter(function ($row) use ($threshold) {
            $jam = self::normalizeJamForCompare((string) ($row->jam ?? ''));

            return $jam !== '' && $jam > $threshold;
        })->values();

        if ($bucket->isEmpty()) {
            return null;
        }

        $preferred = $bucket->sortByDesc(fn ($row) => self::normalizeJamForCompare((string) ($row->jam ?? '')))->first();

        return self::pickExactOrPreferred($bucket, $preferred, $absensiDate, $time, $targetStatus);
    }

    /**
     * @param  Collection<int, object>  $bucket
     */
    private static function pickExactOrPreferred(
        Collection $bucket,
        ?object $preferred,
        string $absensiDate,
        string $time,
        string $targetStatus
    ): ?object {
        if ($bucket->isEmpty()) {
            return null;
        }

        $exact = $bucket->first(function ($row) use ($absensiDate, $time, $targetStatus) {
            $rowDate = !empty($row->tanggal)
                ? Carbon::parse((string) $row->tanggal)->toDateString()
                : $absensiDate;

            return $rowDate === $absensiDate
                && self::normalizeJamForCompare((string) ($row->jam ?? '')) === $time
                && strcasecmp((string) ($row->status ?? ''), $targetStatus) === 0;
        });

        return $exact ?? $preferred ?? $bucket->first();
    }

    /**
     * Batas pemisah Masuk vs Keluar untuk shift harian — selaras dengan MonthlyAbsensiDataBuilder (time_in + 4 jam).
     */
    public static function resolveDaySplitThreshold(?object $shiftRowOnDate = null): string
    {
        $checkinHms = '08:00:00';
        if ($shiftRowOnDate && !empty($shiftRowOnDate->time_in)) {
            $normalized = self::normalizeJamForCompare((string) $shiftRowOnDate->time_in);
            if ($normalized !== '') {
                $checkinHms = $normalized;
            }
        }

        return date('H:i:s', strtotime($checkinHms . ' +4 hours'));
    }

    private static function isOvernightCheckOutTarget(
        string $correctionDate,
        string $absensiDate,
        string $time,
        ?string $shiftOnDate,
        ?string $shiftPrevDate
    ): bool {
        $onIsOvernight = self::isOvernightShift($shiftOnDate);
        if ($absensiDate > $correctionDate && $onIsOvernight) {
            return true;
        }

        if ($time < ShiftAbsensiPunchRules::OVERNIGHT_KELUAR_BEFORE) {
            return self::isOvernightShift($shiftPrevDate) || $onIsOvernight;
        }

        return false;
    }

    private static function normalizeJamForCompare(string $jam): string
    {
        $jam = str_replace('.', ':', trim($jam));
        if ($jam === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $jam, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
        }

        return $jam;
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
        return self::normalizeJamForCompare($time);
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
