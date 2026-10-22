<?php

namespace App\Services;

use App\Http\Controllers\api\AbsensiController;
use App\Models\Hr\HrRequest;
use App\Models\ShiftKaryawan;
use App\Services\Hr\HrAttendanceDayExcuseLabels;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryAdjustmentAttendanceSummaryService
{
    private const LATE_GRACE_MINUTES = 5;

    /** Pengajuan izin / cuti / lembur via portal HR dihitung dari tanggal ini. */
    public const SYSTEM_REQUESTS_EFFECTIVE_FROM = '2026-10-01';

    /** @var array<string, string> */
    private $defaultShiftOut = [
        'SHREGULAR' => '17:00:00',
        'SHANALYST' => '16:00:00',
        'SHADMSAMPLING' => '16:00:00',
        'SHTEKNISI' => '19:00:00',
        'SHOB' => '15:00:00',
        'SHOB2' => '20:00:00',
        'SHSPVOPFIN' => '18:00:00',
        'SHANALISMIKRO' => '13:00:00',
        'SHKORSAMP1' => '17:00:00',
        'SHKORSAMP2' => '20:00:00',
        'SWOB' => '12:00:00',
        'SWOB2' => '18:00:00',
        'SHSECURITY' => '20:00:00',
        'SHSECURITY2' => '08:00:00',
        'SHSECURITYGO1' => '19:00:00',
        'SHSECURITYGO2' => '15:00:00',
        '24JAM' => '08:00:00',
    ];

    public function build(int $employeeId, int $months = 3): array
    {
        Carbon::setLocale('id');
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $monthKeys = $this->resolveMonthKeys($months);
        $periodStart = Carbon::createFromFormat('Y-m', $monthKeys[0])->startOfMonth();
        $periodEnd = Carbon::now('Asia/Jakarta')->startOfDay();

        /** @var AbsensiController $absensiController */
        $absensiController = app(AbsensiController::class);

        $periodFrom = $periodStart->toDateString();
        $periodTo = $periodEnd->toDateString();

        $shiftOutIndex = $this->fetchShiftOutIndex($employeeId, $periodFrom, $periodTo);
        $excuseLabels = HrAttendanceDayExcuseLabels::forSingleKaryawan($employeeId, $periodFrom, $periodTo);
        $overtimeDates = $this->fetchApprovedOvertimeDates($employeeId, $periodFrom, $periodTo);

        $allRecords = [];
        $monthly = [];

        foreach ($monthKeys as $monthKey) {
            $records = $absensiController->getMonthlyAttendance($employeeId, $monthKey);
            $monthSummary = $this->emptySummary();
            $monthDaily = [];

            foreach ($records as $row) {
                $processed = $this->processAttendanceRow(
                    $row,
                    $today,
                    $shiftOutIndex,
                    $excuseLabels,
                    $overtimeDates
                );
                if ($processed === null) {
                    continue;
                }

                $monthDaily[] = $processed;
                $allRecords[] = $processed;
                $this->accumulateSummary($monthSummary, $processed);
            }

            usort($monthDaily, fn ($left, $right) => strcmp($right['tanggal'], $left['tanggal']));

            $monthly[] = [
                'month' => $monthKey,
                'label' => Carbon::createFromFormat('Y-m', $monthKey)->translatedFormat('F Y'),
                'summary' => $monthSummary,
                'daily' => $monthDaily,
                'attendance_days' => $monthSummary['hadir'],
                'total_records' => count($monthDaily),
            ];
        }

        $summary = $this->emptySummary();
        foreach ($allRecords as $row) {
            $this->accumulateSummary($summary, $row);
        }

        usort($allRecords, fn ($left, $right) => strcmp($right['tanggal'], $left['tanggal']));

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'period_label' => $periodStart->translatedFormat('M Y') . ' – ' . $periodEnd->translatedFormat('M Y'),
            'months_count' => $months,
            'summary' => $summary,
            'attendance_days' => $summary['hadir'],
            'total_records' => array_sum(array_column($monthly, 'total_records')),
            'monthly' => $monthly,
            'daily' => array_slice($allRecords, 0, 120),
            'rules' => [
                'late' => '',
                'overtime' => '',
            ],
            'system_effective_from' => self::SYSTEM_REQUESTS_EFFECTIVE_FROM,
            'system_effective_note' => 'Pengajuan izin, cuti, dan lembur melalui system HR efektif per Oktober 2026. '
                . 'Bulan sebelum Oktober hanya menampilkan hadir, lembur (absensi), dan alpha dari mesin absensi.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildMonthDaily(int $employeeId, string $monthKey): array
    {
        Carbon::setLocale('id');
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return [];
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $monthStart = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $periodEnd = Carbon::now('Asia/Jakarta')->startOfDay();
        if ($monthEnd->gt($periodEnd)) {
            $monthEnd = $periodEnd;
        }

        $periodFrom = $monthStart->toDateString();
        $periodTo = $monthEnd->toDateString();

        /** @var AbsensiController $absensiController */
        $absensiController = app(AbsensiController::class);

        $shiftOutIndex = $this->fetchShiftOutIndex($employeeId, $periodFrom, $periodTo);
        $excuseLabels = HrAttendanceDayExcuseLabels::forSingleKaryawan($employeeId, $periodFrom, $periodTo);
        $overtimeDates = $this->fetchApprovedOvertimeDates($employeeId, $periodFrom, $periodTo);

        $records = $absensiController->getMonthlyAttendance($employeeId, $monthKey);
        $monthDaily = [];

        foreach ($records as $row) {
            $processed = $this->processAttendanceRow(
                $row,
                $today,
                $shiftOutIndex,
                $excuseLabels,
                $overtimeDates
            );
            if ($processed === null) {
                continue;
            }
            $monthDaily[] = $processed;
        }

        usort($monthDaily, fn ($left, $right) => strcmp($right['tanggal'], $left['tanggal']));

        return $monthDaily;
    }

    /**
     * Semua hari kalender untuk tampilan list (termasuk akhir pekan / libur).
     *
     * @return list<array<string, mixed>>
     */
    public function buildMonthDailyForDisplay(int $employeeId, string $monthKey): array
    {
        Carbon::setLocale('id');
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return [];
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $monthStart = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $periodEnd = Carbon::now('Asia/Jakarta')->startOfDay();
        if ($monthEnd->gt($periodEnd)) {
            $monthEnd = $periodEnd;
        }

        $periodFrom = $monthStart->toDateString();
        $periodTo = $monthEnd->toDateString();

        /** @var AbsensiController $absensiController */
        $absensiController = app(AbsensiController::class);

        $shiftOutIndex = $this->fetchShiftOutIndex($employeeId, $periodFrom, $periodTo);
        $excuseLabels = HrAttendanceDayExcuseLabels::forSingleKaryawan($employeeId, $periodFrom, $periodTo);
        $overtimeDates = $this->fetchApprovedOvertimeDates($employeeId, $periodFrom, $periodTo);

        $records = $absensiController->getMonthlyAttendance($employeeId, $monthKey);
        $recordsByDate = [];
        foreach ($records as $row) {
            $dateKey = (string) ($row['tanggal'] ?? '');
            if ($dateKey === '') {
                continue;
            }
            if (isset($row['karyawan_id']) && (int) $row['karyawan_id'] > 0 && (int) $row['karyawan_id'] !== $employeeId) {
                continue;
            }
            $recordsByDate[$dateKey] = $row;
        }

        $monthDaily = [];
        $year = (int) $monthStart->format('Y');
        $monthNum = (int) $monthStart->format('m');
        $lastDay = (int) cal_days_in_month(CAL_GREGORIAN, $monthNum, $year);

        for ($day = 1; $day <= $lastDay; $day++) {
            $tanggal = sprintf('%04d-%02d-%02d', $year, $monthNum, $day);
            if ($tanggal > $today) {
                continue;
            }

            $row = $recordsByDate[$tanggal] ?? $this->syntheticMonthDayRow($tanggal);
            $display = $this->processAttendanceDisplayRow(
                $row,
                $today,
                $shiftOutIndex,
                $excuseLabels,
                $overtimeDates
            );
            if ($display === null) {
                continue;
            }
            $monthDaily[] = $display;
        }

        return $monthDaily;
    }

    private function resolveMonthKeys(int $months): array
    {
        $keys = [];
        $cursor = Carbon::now('Asia/Jakarta')->startOfMonth();

        for ($index = 0; $index < $months; $index++) {
            $keys[] = $cursor->copy()->subMonths($index)->format('Y-m');
        }

        return array_reverse($keys);
    }

    private function fetchShiftOutIndex(int $employeeId, string $startDate, string $endDate): array
    {
        $rows = ShiftKaryawan::where('karyawan_id', $employeeId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get(['tanggal', 'shift', 'time_out']);

        $index = [];
        foreach ($rows as $row) {
            $index[Carbon::parse($row->tanggal)->toDateString()] = [
                'shift' => strtoupper(trim((string) $row->shift)),
                'time_out' => $row->time_out,
            ];
        }

        return $index;
    }

    private function processAttendanceDisplayRow(
        array $row,
        string $today,
        array $shiftOutIndex,
        array $excuseLabels,
        array $overtimeDates
    ): ?array {
        $tanggal = $row['tanggal'] ?? '';
        if ($tanggal === '' || $tanggal > $today) {
            return null;
        }

        $shift = strtoupper(trim((string) ($row['shift'] ?? '')));
        $hari = (string) ($row['hari'] ?? '');
        $hasPunch = $this->rowHasAttendancePunch($row);

        if ($this->isWeekendDayName($hari) && !$hasPunch) {
            return $this->buildDisplayDayRow($row, 'weekend', 'Akhir pekan', '—', null, null, null);
        }

        $processed = $this->processAttendanceRow($row, $today, $shiftOutIndex, $excuseLabels, $overtimeDates);
        if ($processed === null) {
            if (in_array($shift, ['LIBUR', 'OFF'], true)) {
                $label = $shift === 'LIBUR' ? 'Libur' : 'OFF';

                return $this->buildDisplayDayRow($row, 'workday', $label, $label, null, null, null);
            }

            return null;
        }

        $category = (string) ($processed['day_category'] ?? 'workday');
        $dayKind = in_array($category, ['izin', 'cuti', 'alpa'], true) ? $category : 'workday';

        return array_merge($processed, [
            'day_kind' => $dayKind,
            'shift_label' => $processed['shift'] ?? ($shift ?: 'SHREGULAR'),
            'date_label' => $this->formatDisplayDateLabel($tanggal),
        ]);
    }

    private function rowHasAttendancePunch(array $row): bool
    {
        $masuk = trim((string) ($row['masuk'] ?? ''));
        $keluar = trim((string) ($row['keluar'] ?? ''));

        return $masuk !== '' || $keluar !== '';
    }

    private function isWeekendDayName(string $hari): bool
    {
        return in_array($hari, ['Sabtu', 'Minggu'], true);
    }

    private function syntheticMonthDayRow(string $tanggal): array
    {
        Carbon::setLocale('id');
        $date = Carbon::parse($tanggal);

        return [
            'tanggal' => $tanggal,
            'hari' => ucfirst($date->translatedFormat('dddd')),
            'masuk' => '',
            'keluar' => '',
            'selisih' => '',
            'jam_kerja' => '',
            'shift' => '',
        ];
    }

    private function buildDisplayDayRow(
        array $row,
        string $dayKind,
        string $status,
        string $shiftLabel,
        ?string $masuk,
        ?string $keluar,
        ?string $keterangan
    ): array {
        $tanggal = (string) ($row['tanggal'] ?? '');

        return [
            'tanggal' => $tanggal,
            'hari' => $row['hari'] ?? '',
            'date_label' => $this->formatDisplayDateLabel($tanggal),
            'shift' => $shiftLabel,
            'shift_label' => $shiftLabel,
            'day_kind' => $dayKind,
            'jam_masuk' => $masuk,
            'jam_keluar' => $keluar,
            'selisih' => null,
            'status' => $status,
            'keterangan' => $keterangan,
        ];
    }

    private function formatDisplayDateLabel(string $tanggal): string
    {
        try {
            return Carbon::parse($tanggal)->translatedFormat('j F Y');
        } catch (\Throwable $e) {
            return $tanggal;
        }
    }

    private function processAttendanceRow(
        array $row,
        string $today,
        array $shiftOutIndex,
        array $excuseLabels,
        array $overtimeDates
    ): ?array {
        $tanggal = $row['tanggal'] ?? '';
        if ($tanggal === '' || $tanggal > $today) {
            return null;
        }

        $shift = strtoupper(trim((string) ($row['shift'] ?? '')));
        $hari = (string) ($row['hari'] ?? '');
        $masuk = trim((string) ($row['masuk'] ?? ''));
        $keluar = trim((string) ($row['keluar'] ?? ''));
        $selisih = trim((string) ($row['selisih'] ?? ''));
        $isWeekend = in_array($hari, ['Sabtu', 'Minggu'], true);

        if (in_array($shift, ['LIBUR', 'OFF'], true)) {
            return null;
        }

        if ($isWeekend && $masuk === '') {
            return null;
        }

        $excuse = trim((string) ($excuseLabels[$tanggal] ?? ''));
        $usesSystemRequests = $tanggal >= self::SYSTEM_REQUESTS_EFFECTIVE_FROM;
        $systemLembur = $usesSystemRequests && isset($overtimeDates[$tanggal]);

        if ($masuk === '') {
            if ($usesSystemRequests && $excuse !== '') {
                $kind = $this->classifyExcuseLabel($excuse);

                return $this->buildDailyPayload(
                    $row,
                    $shift ?: 'SHREGULAR',
                    '',
                    '',
                    '',
                    $kind === 'cuti' ? 'Cuti' : 'Izin',
                    false,
                    false,
                    true,
                    $kind,
                    $excuse,
                    false
                );
            }

            return $this->buildDailyPayload(
                $row,
                $shift ?: 'SHREGULAR',
                '',
                '',
                '',
                'Alpha',
                false,
                false,
                true,
                'alpa',
                $excuse !== '' ? $excuse : null,
                false
            );
        }

        $lateMinutes = $this->parseLateMinutesFromSelisih($selisih);
        $isLate = $lateMinutes !== null && $lateMinutes > self::LATE_GRACE_MINUTES;
        $punchOvertime = $this->isOvertimeDay($shift, $keluar, $shiftOutIndex[$tanggal] ?? null);
        $isOvertime = $usesSystemRequests ? ($systemLembur || $punchOvertime) : $punchOvertime;

        $status = $isLate ? 'Telat' : 'Hadir';
        if ($isOvertime) {
            $status .= ' + Lembur';
        }
        if ($keluar === '') {
            $status = 'Tidak lengkap';
        }

        return $this->buildDailyPayload(
            $row,
            $shift ?: 'SHREGULAR',
            $masuk,
            $keluar,
            $selisih,
            $status,
            $isLate,
            $isOvertime,
            true,
            'hadir',
            $excuse !== '' ? $excuse : null,
            $systemLembur
        );
    }

    private function buildDailyPayload(
        array $row,
        string $shift,
        string $masuk,
        string $keluar,
        string $selisih,
        string $status,
        bool $isLate,
        bool $isOvertime,
        bool $countsAsEligible,
        string $dayCategory,
        ?string $keterangan,
        bool $systemLembur
    ): array {
        return [
            'tanggal' => $row['tanggal'] ?? '',
            'hari' => $row['hari'] ?? '',
            'shift' => $shift,
            'jam_masuk' => $masuk !== '' ? $masuk : null,
            'jam_keluar' => $keluar !== '' ? $keluar : null,
            'selisih' => $selisih !== '' ? $selisih : null,
            'jam_kerja' => trim((string) ($row['jam_kerja'] ?? '')) ?: null,
            'status' => $status,
            'keterangan' => $keterangan,
            'is_late' => $isLate,
            'is_overtime' => $isOvertime,
            'system_lembur' => $systemLembur,
            'day_category' => $dayCategory,
            'counts_as_eligible' => $countsAsEligible,
        ];
    }

    private function classifyExcuseLabel(string $label): string
    {
        $normalized = mb_strtolower($label);
        if (str_contains($normalized, 'cuti') || str_contains($normalized, 'pengganti hari libur')) {
            return 'cuti';
        }

        return 'izin';
    }

    /** @return array<string, true> */
    private function fetchApprovedOvertimeDates(int $employeeId, string $fromYmd, string $toYmd): array
    {
        $rows = DB::table('hr_overtime_participant as op')
            ->join('hr_request as hr', 'hr.id', '=', 'op.request_id')
            ->join('hr_overtime_detail as od', 'od.request_id', '=', 'hr.id')
            ->where('op.karyawan_id', $employeeId)
            ->where('op.is_active', true)
            ->where('hr.request_type', HrRequest::TYPE_OVERTIME)
            ->where('hr.is_active', true)
            ->whereIn('hr.status', WorkflowStatus::formsStatApprovedOvertimeStatuses())
            ->where('od.end_date', '>=', self::SYSTEM_REQUESTS_EFFECTIVE_FROM)
            ->where('od.start_date', '<=', $toYmd)
            ->where('od.end_date', '>=', $fromYmd)
            ->get(['od.start_date', 'od.end_date']);

        $dates = [];
        foreach ($rows as $row) {
            try {
                $cursor = Carbon::parse($row->start_date)->startOfDay();
                $last = Carbon::parse($row->end_date)->startOfDay();
            } catch (\Throwable $e) {
                continue;
            }

            while ($cursor->lte($last)) {
                $ymd = $cursor->toDateString();
                if ($ymd >= self::SYSTEM_REQUESTS_EFFECTIVE_FROM && $ymd >= $fromYmd && $ymd <= $toYmd) {
                    $dates[$ymd] = true;
                }
                $cursor->addDay();
            }
        }

        return $dates;
    }

    private function isOvertimeDay(string $shift, string $keluar, ?array $shiftMeta): bool
    {
        if ($keluar === '' || $keluar === '00:00:00') {
            return false;
        }

        $shiftNorm = strtoupper(trim($shift));
        if (in_array($shiftNorm, ['LIBUR', 'OFF'], true)) {
            return false;
        }

        $keluarSeconds = $this->toSeconds($keluar);
        if ($keluarSeconds === null) {
            return false;
        }

        if ($shiftNorm === '' || $shiftNorm === 'SHREGULAR') {
            return $keluarSeconds > $this->toSeconds('18:00:00');
        }

        $scheduledOut = null;
        if ($shiftMeta && !empty($shiftMeta['time_out'])) {
            $scheduledOut = $this->toSeconds($shiftMeta['time_out']);
        }

        if ($scheduledOut === null) {
            $defaultOut = $this->defaultShiftOut[$shiftNorm] ?? '17:00:00';
            $scheduledOut = $this->toSeconds($defaultOut);
        }

        if ($scheduledOut === null) {
            return false;
        }

        return $keluarSeconds > ($scheduledOut + 3600);
    }

    private function accumulateSummary(array &$summary, array $row): void
    {
        if (empty($row['counts_as_eligible'])) {
            return;
        }

        $summary['eligible_days']++;

        $category = (string) ($row['day_category'] ?? '');
        if ($category === 'hadir') {
            $summary['hadir']++;
        } elseif ($category === 'izin') {
            $summary['izin']++;
        } elseif ($category === 'cuti') {
            $summary['cuti']++;
        } elseif ($category === 'alpa') {
            $summary['alpa']++;
        }

        if (!empty($row['is_late'])) {
            $summary['telat']++;
        } elseif ($category === 'hadir') {
            $summary['tepat_waktu']++;
        }

        if (!empty($row['is_overtime'])) {
            $summary['lembur']++;
        }

        if (($row['status'] ?? '') === 'Tidak lengkap') {
            $summary['tidak_lengkap']++;
        }
    }

    private function emptySummary(): array
    {
        return [
            'eligible_days' => 0,
            'hadir' => 0,
            'izin' => 0,
            'cuti' => 0,
            'lembur' => 0,
            'alpa' => 0,
            'alpha' => 0,
            'telat' => 0,
            'tepat_waktu' => 0,
            'libur' => 0,
            'off' => 0,
            'tidak_lengkap' => 0,
        ];
    }

    private function parseLateMinutesFromSelisih(string $selisih): ?int
    {
        if ($selisih === '' || strpos($selisih, '-') !== 0) {
            return null;
        }

        if (!preg_match('/^-(\d+)m$/', $selisih, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    private function toSeconds(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '00:00:00') {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $match)) {
            return null;
        }

        return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (int) ($match[3] ?? 0);
    }
}
