<?php

namespace App\Services\Hr;

use App\Models\MasterShiftKaryawan;
use Illuminate\Support\Collection;

/**
 * Aturan punch shift malam lintas hari — dinamis mengikuti master_shift_karyawan (selaras MesinAbsenHandler & AbsensiController).
 */
final class ShiftAbsensiPunchRules
{
    public const GREATDAY_MESIN = 'GREATDAY';

    public const SECURITY2_MASUK_FROM = '18:00:00';

    public const OVERNIGHT_KELUAR_BEFORE = '14:00:00';

    public static function inferProductionStatus(
        ?string $shiftCode,
        string $jam,
        ?string $timeIn = null,
        ?string $timeOut = null,
        ?string $connection = null
    ): string {
        $jamNorm = self::normalizeJamString($jam);
        if ($jamNorm === null) {
            return 'Masuk';
        }

        if (MasterShiftKaryawan::isNightShift($shiftCode, $timeIn, $timeOut, $connection)) {
            $bounds = MasterShiftKaryawan::resolveNightShiftBounds($shiftCode, $timeIn, $timeOut, $connection);

            return $jamNorm >= $bounds['masuk_from'] ? 'Masuk' : 'Keluar';
        }

        return $jamNorm > self::OVERNIGHT_KELUAR_BEFORE ? 'Keluar' : 'Masuk';
    }

    /**
     * Masuk shift malam lintas hari: punch malam (>= masuk_from), utamakan GREATDAY lalu jam terawal.
     *
     * @param  Collection<int, object>  $rowsToday
     */
    public static function pickNightShiftMasukRow(
        Collection $rowsToday,
        ?string $shiftCode = null,
        ?string $timeIn = null,
        ?string $timeOut = null,
        ?string $connection = null
    ): ?object {
        if ($rowsToday->isEmpty()) {
            return null;
        }

        $bounds = MasterShiftKaryawan::resolveNightShiftBounds($shiftCode, $timeIn, $timeOut, $connection);

        $evening = $rowsToday->filter(
            fn ($row) => self::normalizeJamString((string) $row->jam) >= $bounds['masuk_from']
        );
        if ($evening->isNotEmpty()) {
            return self::preferGreatdayThenEarliestJam($evening);
        }

        $maxJam = $rowsToday->max('jam');
        if ($maxJam === null || (string) $maxJam <= $bounds['keluar_before']) {
            return null;
        }

        return $rowsToday->first(fn ($row) => (string) $row->jam === (string) $maxJam);
    }

    /**
     * Alias kompatibilitas untuk shift malam SHSECURITY2 (20:00–08:00).
     *
     * @param  Collection<int, object>  $rowsToday
     */
    public static function pickSecurity2MasukRow(Collection $rowsToday): ?object
    {
        return self::pickNightShiftMasukRow($rowsToday, 'SHSECURITY2');
    }

    /**
     * Pulang shift malam: punch keesokan pagi (< keluar_before), utamakan GREATDAY lalu jam terawal.
     *
     * @param  Collection<int, object>  $rowsNext
     */
    public static function pickOvernightKeluarRow(
        Collection $rowsNext,
        string $keluarBefore = self::OVERNIGHT_KELUAR_BEFORE
    ): ?object {
        if ($rowsNext->isEmpty()) {
            return null;
        }

        $morning = $rowsNext->filter(
            fn ($row) => self::normalizeJamString((string) $row->jam) < $keluarBefore
        );
        if ($morning->isEmpty()) {
            return null;
        }

        return self::preferGreatdayThenEarliestJam($morning);
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private static function preferGreatdayThenEarliestJam(Collection $rows): ?object
    {
        $greatday = $rows->filter(
            fn ($row) => strtoupper(trim((string) ($row->kode_mesin ?? ''))) === self::GREATDAY_MESIN
        );
        $pool = $greatday->isNotEmpty() ? $greatday : $rows;
        $minJam = $pool->min('jam');
        if ($minJam === null) {
            return null;
        }

        return $pool->first(fn ($row) => (string) $row->jam === (string) $minJam);
    }

    private static function normalizeJamString(string $jam): ?string
    {
        $jam = str_replace('.', ':', trim($jam));
        if ($jam === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $jam, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
        }

        $ts = strtotime($jam);

        return $ts ? date('H:i:s', $ts) : null;
    }
}
