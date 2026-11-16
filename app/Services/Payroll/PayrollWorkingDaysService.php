<?php

namespace App\Services\Payroll;

use App\Models\RekapLiburKalender;
use App\Models\ShiftKaryawan;
use App\Services\Hr\MonthlyAbsensiDataBuilder;
use Carbon\Carbon;

class PayrollWorkingDaysService
{
    /** @return list<int> id_jabatan Senin–Sabtu — dari config/payroll.php (env PAYROLL_JABATAN_INCLUDE_SATURDAY). */
    public static function jabatanIncludeSaturday(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $ids = config('payroll.jabatan_include_saturday', [35, 36, 94]);
        if (!is_array($ids)) {
            $cached = [35, 36, 94];

            return $cached;
        }
        $cached = array_values(array_map('intval', $ids));

        return $cached;
    }

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

        if ($idJabatan !== null && in_array($idJabatan, self::jabatanIncludeSaturday(), true)) {
            return count(self::eligibleWorkDateIndexForKaryawan($karyawanId, $periodeYm, $idJabatan));
        }

        $counts = self::calendarCountsForMonth($periodeYm);

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
            && in_array($idJabatan, self::jabatanIncludeSaturday(), true);

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

    /**
     * Kalender hari kerja per karyawan (Senin–Sabtu: hari shift off/libur tidak wajib masuk / tidak alpa).
     *
     * @param  iterable<int, array<string, mixed>>|null  $rekapRows
     * @return array<string, true>
     */
    public static function eligibleWorkDateIndexForKaryawan(
        int $karyawanId,
        string $periodeYm,
        ?int $idJabatan,
        ?iterable $rekapRows = null
    ): array {
        $index = self::eligibleWorkDateIndex($periodeYm, $idJabatan);

        if ($idJabatan === null || !in_array($idJabatan, self::jabatanIncludeSaturday(), true)) {
            return $index;
        }

        self::removeScheduledRestDaysFromIndex($index, $karyawanId, $periodeYm, $rekapRows);

        return $index;
    }

    /**
     * @param  array<string, true>  $index
     * @param  iterable<int, array<string, mixed>>|null  $rekapRows
     */
    private static function removeScheduledRestDaysFromIndex(
        array &$index,
        int $karyawanId,
        string $periodeYm,
        ?iterable $rekapRows = null
    ): void {
        $parts = explode('-', $periodeYm);
        $start = sprintf('%s-%s-01', $parts[0] ?? '1970', $parts[1] ?? '01');
        $end = date('Y-m-t', strtotime($start));

        $shiftByDate = ShiftKaryawan::query()
            ->select('tanggal', 'shift')
            ->where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->keyBy(fn ($row) => (string) $row->tanggal);

        foreach ($shiftByDate as $ymd => $row) {
            if (!isset($index[$ymd])) {
                continue;
            }
            if (MonthlyAbsensiDataBuilder::isRestDayShift($row->shift ?? null)) {
                unset($index[$ymd]);
            }
        }

        if ($rekapRows === null) {
            return;
        }

        foreach ($rekapRows as $data) {
            $row = is_array($data) ? $data : (array) $data;
            $tanggal = (string) ($row['tanggal'] ?? '');
            if ($tanggal === '' || !isset($index[$tanggal])) {
                continue;
            }
            if (MonthlyAbsensiDataBuilder::isRestDayShift($row['shift'] ?? null)) {
                unset($index[$tanggal]);
            }
        }
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
        string $periodeYm,
        ?int $karyawanId = null
    ): array {
        $eligible = $karyawanId !== null
            ? self::eligibleWorkDateIndexForKaryawan($karyawanId, $periodeYm, $idJabatan, $rekapRows)
            : self::eligibleWorkDateIndex($periodeYm, $idJabatan);
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
        $satIds = self::jabatanIncludeSaturday();
        if ($satIds === []) {
            return (string) (int) $monFri;
        }

        return 'CASE WHEN master_karyawan.id_jabatan IN (' . implode(',', $satIds) . ') THEN '
            . (int) $monSat . ' ELSE ' . (int) $monFri . ' END';
    }

    /** Preview payroll: masuk/hari kerja sudah di rekap (generate absensi), tanpa hitung kalender/jabatan di SQL. */
    public static function sqlShowDataMasukKerja(): string
    {
        return 'IFNULL(JSON_LENGTH(rekap_masuk_kerja.tanggal), 0)';
    }

    /**
     * Hari kerja untuk preview payroll: rekap/payroll dulu, lalu kalender+jamatan bila kolom rekap belum terisi.
     */
    public static function sqlShowDataHariKerja(?string $periodeYm = null): string
    {
        $fromStored = 'IFNULL(rekap_masuk_kerja.hari_kerja, IFNULL(payroll.hari_kerja, 0))';
        if ($periodeYm === null || !preg_match('/^\d{4}-\d{2}$/', $periodeYm)) {
            return $fromStored;
        }

        $counts = self::calendarCountsForMonth($periodeYm);
        $fallback = self::sqlFallbackHariKerjaFromJabatan($counts['mon_fri'], $counts['mon_sat']);

        return 'COALESCE(rekap_masuk_kerja.hari_kerja, payroll.hari_kerja, ' . $fallback . ', 0)';
    }

    public static function sqlShowDataTidakHadir(?string $periodeYm = null): string
    {
        $hari = self::sqlShowDataHariKerja($periodeYm);
        $masuk = self::sqlShowDataMasukKerja();

        return 'GREATEST((' . $hari . ' - ' . $masuk . '), 0)';
    }

    /**
     * Kolom preview generate: hitung dari rekap, tapi baris yang sudah submit memakai snapshot payroll.
     */
    public static function sqlPreviewOrStoredHariKerja(?string $periodeYm = null): string
    {
        return 'CASE WHEN payroll.id IS NULL THEN ' . self::sqlShowDataHariKerja($periodeYm)
            . ' ELSE IFNULL(payroll.hari_kerja, 0) END';
    }

    public static function sqlPreviewOrStoredTidakHadir(?string $periodeYm = null): string
    {
        return 'CASE WHEN payroll.id IS NULL THEN ' . self::sqlShowDataTidakHadir($periodeYm)
            . ' ELSE IFNULL(payroll.tidak_hadir, 0) END';
    }
}
