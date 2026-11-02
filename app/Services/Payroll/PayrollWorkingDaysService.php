<?php

namespace App\Services\Payroll;

use App\Models\RekapLiburKalender;
use App\Models\ShiftKaryawan;
use Carbon\Carbon;

class PayrollWorkingDaysService
{
    /** Jabatan dengan hari kerja Senin–Sabtu (kalender + Sabtu yang bukan libur nasional di JSON). */
    public const JABATAN_INCLUDE_SATURDAY = [35, 36, 94];

    /** Shift security/24 jam — hari kerja expected = jumlah jadwal shift di bulan. */
    public const PER_SHIFT_CODES = [
        'SHSECURITY',
        'SHSECURITY2',
        'SHSECURITYGO1',
        'SHSECURITYGO2',
        '24jam',
        '24JAM',
    ];

    /** Shift yang masuk kerja dihitung per hari shift (tanpa filter kalender Sen–Jum). */
    public const MASUK_PER_SHIFT_CODES = [
        'SHOB',
        'SHOB2',
        'SHSECURITY',
        'SHSECURITY2',
        'SHSECURITYGO1',
        'SHSECURITYGO2',
        '24jam',
        '24JAM',
    ];

    /**
     * @return array{mon_fri: int, mon_sat: int, dates: list<string>}
     */
    public static function calendarCountsForMonth(string $periodeYm): array
    {
        $parts = explode('-', $periodeYm);
        $year = $parts[0] ?? date('Y');
        $month = $parts[1] ?? date('m');

        $record = RekapLiburKalender::where('tahun', $year)
            ->where('is_active', true)
            ->first();

        $monthDates = [];
        if ($record && !empty($record->tanggal)) {
            $decoded = json_decode($record->tanggal, true);
            if (is_array($decoded)) {
                $monthDates = $decoded[$periodeYm] ?? [];
                if (!is_array($monthDates)) {
                    $monthDates = [];
                }
            }
        }

        $normalized = [];
        foreach ($monthDates as $date) {
            if ($date === null || $date === '') {
                continue;
            }
            try {
                $normalized[] = Carbon::parse($date)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
        }
        $normalized = array_values(array_unique($normalized));

        $monFri = 0;
        $monSat = 0;
        foreach ($normalized as $ymd) {
            $dow = (int) Carbon::parse($ymd)->dayOfWeekIso; // 1=Mon … 7=Sun
            if ($dow >= 1 && $dow <= 5) {
                $monFri++;
                $monSat++;
            } elseif ($dow === 6) {
                $monSat++;
            }
        }

        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $inCalendar = array_fill_keys($normalized, true);

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->dayOfWeekIso !== 6) {
                continue;
            }
            $ymd = $day->format('Y-m-d');
            if (isset($inCalendar[$ymd])) {
                continue;
            }
            // Sabtu operasional (35/36/94) bila tidak tercatat libur di kalender bulan ini.
            $monSat++;
        }

        if ($normalized === []) {
            $monFri = self::fallbackWeekdayCount($start, $end, false);
            $monSat = self::fallbackWeekdayCount($start, $end, true);
        }

        return [
            'mon_fri' => $monFri,
            'mon_sat' => $monSat,
            'dates' => $normalized,
        ];
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

    public static function scheduledShiftCount(int $karyawanId, string $periodeYm): int
    {
        $parts = explode('-', $periodeYm);
        $start = sprintf('%s-%s-01', $parts[0] ?? '1970', $parts[1] ?? '01');
        $end = date('Y-m-t', strtotime($start));

        return (int) ShiftKaryawan::query()
            ->where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal', [$start, $end])
            ->whereNotNull('shift')
            ->where('shift', '<>', '')
            ->where('shift', '<>', 'off')
            ->whereIn('shift', self::PER_SHIFT_CODES)
            ->count();
    }

    /**
     * Hari kerja expected per karyawan (dipanggil saat generate rekap masuk kerja).
     */
    public static function expectedHariKerjaForKaryawan(int $karyawanId, ?int $idJabatan, string $periodeYm): int
    {
        $shiftCount = self::scheduledShiftCount($karyawanId, $periodeYm);
        if ($shiftCount > 0) {
            return $shiftCount;
        }

        $counts = self::calendarCountsForMonth($periodeYm);
        if ($idJabatan !== null && in_array($idJabatan, self::JABATAN_INCLUDE_SATURDAY, true)) {
            return $counts['mon_sat'];
        }

        return $counts['mon_fri'];
    }

    /**
     * Tanggal yang boleh dihitung sebagai hari masuk (selain shift security/24 jam).
     *
     * @return array<string, true>
     */
    public static function eligibleWorkDateIndex(string $periodeYm, ?int $idJabatan): array
    {
        $includeSaturday = $idJabatan !== null
            && in_array($idJabatan, self::JABATAN_INCLUDE_SATURDAY, true);

        $counts = self::calendarCountsForMonth($periodeYm);
        $index = [];

        foreach ($counts['dates'] as $ymd) {
            $dow = (int) Carbon::parse($ymd)->dayOfWeekIso;
            if ($dow >= 1 && $dow <= 5) {
                $index[$ymd] = true;
            } elseif ($includeSaturday && $dow === 6) {
                $index[$ymd] = true;
            }
        }

        if (!$includeSaturday) {
            return $index;
        }

        $parts = explode('-', $periodeYm);
        $start = Carbon::createFromDate((int) ($parts[0] ?? date('Y')), (int) ($parts[1] ?? date('m')), 1);
        $end = $start->copy()->endOfMonth();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->dayOfWeekIso !== 6) {
                continue;
            }
            $ymd = $day->format('Y-m-d');
            $index[$ymd] = true;
        }

        return $index;
    }

    public static function isMasukPerShift(string $shift): bool
    {
        $shift = trim($shift);

        return $shift !== '' && in_array($shift, self::MASUK_PER_SHIFT_CODES, true);
    }

    /**
     * Apakah tanggal + shift ini masuk ke rekap masuk kerja (setelah absen lengkap).
     *
     * @param  array<string, true>|null  $eligibleWorkDates
     */
    public static function shouldCountMasukKerjaDay(
        string $tanggal,
        string $shift,
        ?int $idJabatan,
        string $periodeYm,
        ?array $eligibleWorkDates = null
    ): bool {
        $shift = trim($shift);
        if ($shift === '' || strcasecmp($shift, 'off') === 0) {
            return false;
        }

        if (self::isMasukPerShift($shift)) {
            return true;
        }

        if ($tanggal === '') {
            return false;
        }

        $eligible = $eligibleWorkDates ?? self::eligibleWorkDateIndex($periodeYm, $idJabatan);

        return isset($eligible[$tanggal]);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rekapRows
     * @return list<string>
     */
    public static function collectMasukKerjaDates(
        iterable $rekapRows,
        ?int $idJabatan,
        string $periodeYm
    ): array {
        $eligible = self::eligibleWorkDateIndex($periodeYm, $idJabatan);
        $dates = [];

        foreach ($rekapRows as $data) {
            $row = is_array($data) ? $data : (array) $data;
            $masuk = trim((string) ($row['masuk'] ?? ''));
            $keluar = trim((string) ($row['keluar'] ?? ''));
            if ($masuk === '' || $keluar === '') {
                continue;
            }

            $tanggal = (string) ($row['tanggal'] ?? '');
            $shift = (string) ($row['shift'] ?? '');

            if (!self::shouldCountMasukKerjaDay($tanggal, $shift, $idJabatan, $periodeYm, $eligible)) {
                continue;
            }

            $dates[] = $tanggal;
        }

        return array_values(array_unique($dates));
    }

    /** Fallback ringan di payroll bila rekap lama belum punya kolom hari_kerja. */
    public static function sqlFallbackHariKerjaFromJabatan(int $monFri, int $monSat): string
    {
        $satIds = implode(',', self::JABATAN_INCLUDE_SATURDAY);

        return 'CASE WHEN master_karyawan.id_jabatan IN (' . $satIds . ') THEN '
            . (int) $monSat . ' ELSE ' . (int) $monFri . ' END';
    }

    /** Preview payroll: masuk/hari kerja sudah di rekap (generate absensi), tanpa hitung kalender/jabatan di SQL. */
    public static function sqlShowDataMasukKerja(): string
    {
        return 'IFNULL(JSON_LENGTH(rekap_masuk_kerja.tanggal), 0)';
    }

    public static function sqlShowDataHariKerja(): string
    {
        return 'IFNULL(rekap_masuk_kerja.hari_kerja, IFNULL(payroll.hari_kerja, 0))';
    }

    public static function sqlShowDataTidakHadir(): string
    {
        $hari = self::sqlShowDataHariKerja();
        $masuk = self::sqlShowDataMasukKerja();

        return 'GREATEST((' . $hari . ' - ' . $masuk . '), 0)';
    }
}
