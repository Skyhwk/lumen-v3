<?php

namespace App\Services;

use App\Http\Controllers\api\AbsensiController;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use InvalidArgumentException;

class ProfileAttendanceService
{
    public function build(int $userId, ?string $month = null): array
    {
        Carbon::setLocale('id');

        $employee = MasterKaryawan::where('id', $userId)->where('is_active', 1)->first();
        if (!$employee) {
            return $this->emptyPayload($userId, $month);
        }

        $availableMonths = $this->availableMonths();
        $allowedValues   = array_column($availableMonths, 'value');
        $month           = $month ?: ($allowedValues[0] ?? Carbon::now('Asia/Jakarta')->format('Y-m'));

        if (!in_array($month, $allowedValues, true)) {
            throw new InvalidArgumentException('Bulan di luar rentang 3 bulan terakhir.');
        }

        /** @var AbsensiController $absensiController */
        $absensiController = app(AbsensiController::class);
        $records           = $this->enrichRecords($absensiController->getMonthlyAttendance($userId, $month));
        $today             = Carbon::now('Asia/Jakarta')->toDateString();
        $period            = $this->resolvePeriodLabel($month);

        return [
            'employee' => [
                'id'           => (int) $employee->id,
                'name'         => $employee->nama_lengkap,
                'nik'          => $employee->nik_karyawan,
            ],
            'period' => [
                'value'      => $month,
                'label'      => $period['label'],
                'start'      => $period['start'],
                'end'        => $period['end'],
                'today'      => $today,
                'is_current' => $month === ($allowedValues[0] ?? null),
            ],
            'available_months' => $availableMonths,
            'summary'          => $this->summarize($records, $today),
            'records'          => $records,
        ];
    }

    public function availableMonths(): array
    {
        Carbon::setLocale('id');
        $months = [];
        $now    = Carbon::now('Asia/Jakarta')->startOfMonth();

        for ($index = 0; $index < 3; $index++) {
            $date = $now->copy()->subMonths($index);
            $months[] = [
                'value'      => $date->format('Y-m'),
                'label'      => $date->translatedFormat('F Y'),
                'is_current' => $index === 0,
            ];
        }

        return $months;
    }

    private function summarize(array $records, string $today): array
    {
        $summary = [
            'eligible_days' => 0,
            'hadir'         => 0,
            'alpha'         => 0,
            'telat'         => 0,
            'libur'         => 0,
            'off'           => 0,
            'cuti'          => 0,
            'izin'          => 0,
        ];

        foreach ($records as $row) {
            $tanggal = $row['tanggal'] ?? '';
            if ($tanggal === '' || $tanggal > $today) {
                continue;
            }

            $shift    = strtoupper(trim((string) ($row['shift'] ?? '')));
            $hari     = (string) ($row['hari'] ?? '');
            $isWeekend = in_array($hari, ['Sabtu', 'Minggu'], true);
            $masuk    = trim((string) ($row['masuk'] ?? ''));

            if ($shift === 'LIBUR') {
                $summary['libur']++;
                continue;
            }

            if ($shift === 'OFF') {
                $summary['off']++;
                continue;
            }

            if ($isWeekend && $masuk === '') {
                continue;
            }

            if ($masuk === '' && $tanggal === $today) {
                continue;
            }

            $summary['eligible_days']++;

            if ($masuk !== '') {
                $summary['hadir']++;
                $selisih = trim((string) ($row['selisih'] ?? ''));
                if ($selisih !== '' && strpos($selisih, '-') === 0) {
                    $summary['telat']++;
                }
                continue;
            }

            if (!empty($row['hr_excused'])) {
                $kind = (string) ($row['excuse_kind'] ?? $this->classifyExcuseLabel((string) ($row['keterangan'] ?? '')));
                if ($kind === 'cuti') {
                    $summary['cuti']++;
                } else {
                    $summary['izin']++;
                }
                continue;
            }

            $summary['alpha']++;
        }

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    private function enrichRecords(array $records): array
    {
        foreach ($records as &$row) {
            if (!empty($row['hr_excused'])) {
                $row['excuse_kind'] = $this->classifyExcuseLabel((string) ($row['keterangan'] ?? ''));
            }
        }
        unset($row);

        return $records;
    }

    private function classifyExcuseLabel(string $label): string
    {
        $normalized = mb_strtolower($label);
        if (str_contains($normalized, 'cuti') || str_contains($normalized, 'pengganti hari libur')) {
            return 'cuti';
        }

        return 'izin';
    }

    private function resolvePeriodLabel(string $month): array
    {
        [$year, $monthNum] = explode('-', $month);
        $start = Carbon::createFromDate((int) $year, (int) $monthNum, 1, 'Asia/Jakarta')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        return [
            'label' => $start->translatedFormat('F Y'),
            'start' => $start->toDateString(),
            'end'   => $end->toDateString(),
        ];
    }

    private function emptyPayload(int $userId, ?string $month): array
    {
        $availableMonths = $this->availableMonths();
        $month           = $month ?: ($availableMonths[0]['value'] ?? Carbon::now('Asia/Jakarta')->format('Y-m'));

        return [
            'employee' => [
                'id'   => $userId,
                'name' => null,
                'nik'  => null,
            ],
            'period' => array_merge(
                ['value' => $month, 'today' => Carbon::now('Asia/Jakarta')->toDateString(), 'is_current' => true],
                $this->resolvePeriodLabel($month)
            ),
            'available_months' => $availableMonths,
            'summary'          => [
                'eligible_days' => 0,
                'hadir'         => 0,
                'alpha'         => 0,
                'telat'         => 0,
                'libur'         => 0,
                'off'           => 0,
                'cuti'          => 0,
                'izin'          => 0,
            ],
            'records' => [],
        ];
    }
}
