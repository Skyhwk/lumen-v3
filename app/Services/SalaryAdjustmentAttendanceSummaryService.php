<?php

namespace App\Services;

use App\Http\Controllers\api\AbsensiController;
use App\Models\ShiftKaryawan;
use Carbon\Carbon;

class SalaryAdjustmentAttendanceSummaryService
{
    private const LATE_GRACE_MINUTES = 5;

    /** @var array<string, string> */
    private $defaultShiftOut = [
        'SHREGULAR' => '17:00:00',
        'SHANALYST' => '16:00:00',
        'SHADMSAMPLING' => '16:00:00',
        'SHTEKNISI' => '19:00:00',
        'SHOB' => '15:00:00',
        'SHOB2' => '18:00:00',
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
        $periodStart = Carbon::createFromFormat('Y-m', $monthKeys[count($monthKeys) - 1])->startOfMonth();
        $periodEnd = Carbon::now('Asia/Jakarta')->startOfDay();

        /** @var AbsensiController $absensiController */
        $absensiController = app(AbsensiController::class);

        $shiftOutIndex = $this->fetchShiftOutIndex(
            $employeeId,
            $periodStart->toDateString(),
            $periodEnd->toDateString()
        );

        $allRecords = [];
        $monthly = [];

        foreach ($monthKeys as $monthKey) {
            $records = $absensiController->getMonthlyAttendance($employeeId, $monthKey);
            $monthSummary = $this->emptySummary();

            foreach ($records as $row) {
                $enriched = $this->enrichDailyRow($row, $today, $shiftOutIndex);
                if ($enriched === null) {
                    continue;
                }

                $allRecords[] = $enriched;
                $this->accumulateSummary($monthSummary, $enriched);
            }

            $monthly[] = [
                'month' => $monthKey,
                'label' => Carbon::createFromFormat('Y-m', $monthKey)->translatedFormat('F Y'),
                'summary' => $monthSummary,
                'attendance_days' => $monthSummary['hadir'],
                'total_records' => count(array_filter($records, fn ($row) => !empty($row['masuk'] ?? ''))),
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
        ];
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

    private function enrichDailyRow(array $row, string $today, array $shiftOutIndex): ?array
    {
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

        if ($masuk === '') {
            return null;
        }

        $lateMinutes = $this->parseLateMinutesFromSelisih($selisih);
        $isLate = $lateMinutes !== null && $lateMinutes > self::LATE_GRACE_MINUTES;
        $isOvertime = $this->isOvertimeDay($shift, $keluar, $shiftOutIndex[$tanggal] ?? null);
        $status = $isLate ? 'Telat' : 'Hadir';
        if ($isOvertime) {
            $status .= ' + Lembur';
        }
        if ($masuk !== '' && $keluar === '') {
            $status = 'Tidak lengkap';
        }

        return $this->buildDailyPayload($row, $shift ?: 'SHREGULAR', $masuk, $keluar, $selisih, $status, $isLate, $isOvertime, true);
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
        bool $countsAsEligible
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
            'is_late' => $isLate,
            'is_overtime' => $isOvertime,
            'counts_as_eligible' => $countsAsEligible,
        ];
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
        $shift = strtoupper(trim((string) ($row['shift'] ?? '')));
        $status = (string) ($row['status'] ?? '');

        if (empty($row['counts_as_eligible'])) {
            return;
        }

        $summary['eligible_days']++;

        if (!empty($row['jam_masuk'])) {
            $summary['hadir']++;
        }

        if (!empty($row['is_late'])) {
            $summary['telat']++;
        } elseif (!empty($row['jam_masuk'])) {
            $summary['tepat_waktu']++;
        }

        if (!empty($row['is_overtime'])) {
            $summary['lembur']++;
        }

        if ($status === 'Tidak lengkap') {
            $summary['tidak_lengkap']++;
        }
    }

    private function emptySummary(): array
    {
        return [
            'eligible_days' => 0,
            'hadir' => 0,
            'alpha' => 0,
            'telat' => 0,
            'tepat_waktu' => 0,
            'lembur' => 0,
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
