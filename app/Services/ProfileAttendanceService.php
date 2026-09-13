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
        $records           = $absensiController->getMonthlyAttendance($userId, $month);
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
        ];

        foreach ($records as $row) {
            $tanggal = $row['tanggal'] ?? '';
            if ($tanggal === '' || $tanggal > $today) {
                continue;
            }

            $shift    = strtoupper(trim((string) ($row['shift'] ?? '')));
            $hari     = (string) ($row['hari'] ?? '');
            $isWeekend = in_array($hari, ['Sabtu', 'Minggu'], true);

            if ($shift === 'LIBUR') {
                $summary['libur']++;
                continue;
            }

            if ($shift === 'OFF') {
                $summary['off']++;
                continue;
            }

            if ($isWeekend && empty($row['masuk'])) {
                continue;
            }

            $summary['eligible_days']++;

            if (!empty($row['masuk'])) {
                $summary['hadir']++;
                $selisih = trim((string) ($row['selisih'] ?? ''));
                if ($selisih !== '' && strpos($selisih, '-') === 0) {
                    $summary['telat']++;
                }
                continue;
            }

            $summary['alpha']++;
        }

        return $summary;
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
            ],
            'records' => [],
        ];
    }
}
