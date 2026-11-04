<?php

namespace App\Services\Hr;

use Illuminate\Support\Collection;

/**
 * Aturan punch shift malam (SHSECURITY2) — selaras MesinAbsenHandler & AbsensiController monthly.
 */
final class ShiftAbsensiPunchRules
{
    public const GREATDAY_MESIN = 'GREATDAY';

    public const SECURITY2_MASUK_FROM = '18:00:00';

    public const OVERNIGHT_KELUAR_BEFORE = '14:00:00';

    public static function inferProductionStatus(?string $shiftCode, string $jam): string
    {
        $jamNorm = self::normalizeJamString($jam);
        if ($jamNorm === null) {
            return 'Masuk';
        }

        $shift = strtolower(trim((string) $shiftCode));
        if ($shift === 'shsecurity2') {
            return $jamNorm >= self::SECURITY2_MASUK_FROM ? 'Masuk' : 'Keluar';
        }

        return $jamNorm > self::OVERNIGHT_KELUAR_BEFORE ? 'Keluar' : 'Masuk';
    }

    /**
     * Masuk shift SHSECURITY2: punch malam (>= 18:00), utamakan GREATDAY lalu jam terawal.
     *
     * @param  Collection<int, object>  $rowsToday
     */
    public static function pickSecurity2MasukRow(Collection $rowsToday): ?object
    {
        if ($rowsToday->isEmpty()) {
            return null;
        }

        $evening = $rowsToday->filter(
            fn ($row) => self::normalizeJamString((string) $row->jam) >= self::SECURITY2_MASUK_FROM
        );
        if ($evening->isNotEmpty()) {
            return self::preferGreatdayThenEarliestJam($evening);
        }

        $maxJam = $rowsToday->max('jam');
        if ($maxJam === null || (string) $maxJam <= self::OVERNIGHT_KELUAR_BEFORE) {
            return null;
        }

        return $rowsToday->first(fn ($row) => (string) $row->jam === (string) $maxJam);
    }

    /**
     * Pulang shift malam: punch keesokan pagi (< 14:00), utamakan GREATDAY lalu jam terawal.
     *
     * @param  Collection<int, object>  $rowsNext
     */
    public static function pickOvernightKeluarRow(Collection $rowsNext): ?object
    {
        if ($rowsNext->isEmpty()) {
            return null;
        }

        $morning = $rowsNext->filter(
            fn ($row) => self::normalizeJamString((string) $row->jam) < self::OVERNIGHT_KELUAR_BEFORE
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
