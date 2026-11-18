<?php

namespace App\Services\Hr;

use App\Models\RekapLiburKalender;
use Carbon\Carbon;

class AbsensiPeriodeService
{
    public const GRADE_SUPERVISOR = 'SUPERVISOR';

    public const GRADE_STAFF = 'STAFF';

    public const SUPERVISOR_PERIOD_START_DAY = 16;

    public const SUPERVISOR_PERIOD_END_DAY = 15;

    public static function normalizeGrade(?string $grade): string
    {
        $normalized = strtoupper(trim((string) $grade));
        if ($normalized === 'SPV') {
            return self::GRADE_SUPERVISOR;
        }

        return $normalized;
    }

    public static function usesSupervisorPeriod(?string $grade): bool
    {
        return self::normalizeGrade($grade) === self::GRADE_SUPERVISOR;
    }

    /**
     * @return array{start_ymd: string, end_ymd: string, bulan_ym: string}
     */
    public static function resolveRange(string $bulanYm, ?string $grade = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $bulanYm)) {
            throw new \InvalidArgumentException('Format bulan harus YYYY-MM.');
        }

        if (self::usesSupervisorPeriod($grade)) {
            // Kunci YYYY-MM = bulan awal periode (gaji bulan itu); contoh 2026-10 → 16 Okt – 15 Nov.
            $anchor = Carbon::createFromFormat('Y-m-d', $bulanYm . '-01', 'Asia/Jakarta');
            $start = $anchor->copy()->day(self::SUPERVISOR_PERIOD_START_DAY);
            $end = $anchor->copy()->addMonth()->day(self::SUPERVISOR_PERIOD_END_DAY);

            return [
                'start_ymd' => $start->toDateString(),
                'end_ymd' => $end->toDateString(),
                'bulan_ym' => $bulanYm,
            ];
        }

        $start = Carbon::createFromFormat('Y-m-d', $bulanYm . '-01', 'Asia/Jakarta');
        $end = $start->copy()->endOfMonth();

        return [
            'start_ymd' => $start->toDateString(),
            'end_ymd' => $end->toDateString(),
            'bulan_ym' => $bulanYm,
        ];
    }

    /** Bulan kalender sebelumnya (per staff). */
    public static function resolveTargetBulanYmStaff(?string $asOfYmd = null): string
    {
        $asOf = self::parseAsOf($asOfYmd);

        return $asOf->startOfMonth()->subMonth()->format('Y-m');
    }

    /**
     * Periode SPV (kunci = bulan tgl 16): tutup tgl 15 bulan berikutnya.
     * ≥ tgl 16: periode yang baru tutup = bulan kalender − 1; &lt; tgl 16: − 2 bulan.
     */
    public static function resolveTargetBulanYmSupervisor(?string $asOfYmd = null): string
    {
        $asOf = self::parseAsOf($asOfYmd);

        if ((int) $asOf->day >= self::SUPERVISOR_PERIOD_START_DAY) {
            return $asOf->copy()->subMonth()->format('Y-m');
        }

        return $asOf->copy()->subMonths(2)->format('Y-m');
    }

    public static function periodLabel(string $bulanYm, ?string $grade = null): string
    {
        $range = self::resolveRange($bulanYm, $grade);
        $start = Carbon::parse($range['start_ymd'], 'Asia/Jakarta')->locale('id');
        $end = Carbon::parse($range['end_ymd'], 'Asia/Jakarta')->locale('id');

        return sprintf(
            '%d %s %d – %d %s %d',
            $start->day,
            $start->translatedFormat('M'),
            $start->year,
            $end->day,
            $end->translatedFormat('M'),
            $end->year
        );
    }

    /**
     * @return array{mon_fri: int, mon_sat: int, dates: list<string>}
     */
    public static function calendarCountsForRange(string $startYmd, string $endYmd): array
    {
        $start = Carbon::parse($startYmd, 'Asia/Jakarta')->startOfDay();
        $end = Carbon::parse($endYmd, 'Asia/Jakarta')->startOfDay();

        if ($end->lt($start)) {
            return ['mon_fri' => 0, 'mon_sat' => 0, 'dates' => []];
        }

        $years = array_values(array_unique([$start->year, $end->year]));
        $monthKeys = self::collectMonthKeysBetween($start, $end);

        $allDates = [];
        foreach ($years as $year) {
            $record = RekapLiburKalender::where('tahun', (string) $year)
                ->where('is_active', true)
                ->first();

            if (!$record || empty($record->tanggal)) {
                continue;
            }

            $decoded = json_decode($record->tanggal, true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($monthKeys as $monthKey) {
                if (!isset($decoded[$monthKey]) || !is_array($decoded[$monthKey])) {
                    continue;
                }
                foreach ($decoded[$monthKey] as $date) {
                    if ($date === null || $date === '') {
                        continue;
                    }
                    try {
                        $ymd = Carbon::parse($date)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        continue;
                    }
                    if ($ymd >= $startYmd && $ymd <= $endYmd) {
                        $allDates[] = $ymd;
                    }
                }
            }
        }

        $allDates = array_values(array_unique($allDates));
        sort($allDates);

        $monFri = 0;
        $monSat = 0;
        foreach ($allDates as $ymd) {
            $dow = (int) Carbon::parse($ymd)->dayOfWeekIso;
            if ($dow >= 1 && $dow <= 5) {
                $monFri++;
                $monSat++;
            } elseif ($dow === 6) {
                $monSat++;
            }
        }

        if ($allDates === []) {
            $monFri = self::fallbackWeekdayCount($start, $end, false);
            $monSat = self::fallbackWeekdayCount($start, $end, true);
        } else {
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if ($day->dayOfWeekIso !== 6) {
                    continue;
                }
                $ymd = $day->format('Y-m-d');
                if (!in_array($ymd, $allDates, true)) {
                    $monSat++;
                }
            }
        }

        return [
            'mon_fri' => $monFri,
            'mon_sat' => $monSat,
            'dates' => $allDates,
        ];
    }

    /**
     * @return list<string> YYYY-MM keys between start and end inclusive
     */
    private static function collectMonthKeysBetween(Carbon $start, Carbon $end): array
    {
        $keys = [];
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor->lte($last)) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }

    private static function fallbackWeekdayCount(Carbon $start, Carbon $end, bool $includeSaturday): int
    {
        $count = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $iso = $day->dayOfWeekIso;
            if ($iso >= 1 && $iso <= 5) {
                $count++;
            } elseif ($includeSaturday && $iso === 6) {
                $count++;
            }
        }

        return $count;
    }

    private static function parseAsOf(?string $asOfYmd): Carbon
    {
        if ($asOfYmd !== null && $asOfYmd !== '') {
            return Carbon::parse($asOfYmd, 'Asia/Jakarta');
        }

        return Carbon::now('Asia/Jakarta');
    }
}