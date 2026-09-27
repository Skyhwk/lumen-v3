<?php

namespace App\Services\Greatday;

use App\Models\Greatday\LiburPerusahaan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OfficeCalendarService
{
    private IndonesiaNationalHolidayService $nationalHolidayService;

    public function __construct(IndonesiaNationalHolidayService $nationalHolidayService)
    {
        $this->nationalHolidayService = $nationalHolidayService;
    }

    /**
     * @return array{
     *   data: \Illuminate\Support\Collection,
     *   calendar: array{
     *     events: list<array{tanggal: string, keterangan: string}>,
     *     holiday_dates: list<string>,
     *     additional_working_dates: list<string>,
     *     national_source: ?string,
     *     national_available: bool
     *   }
     * }
     */
    public function forYear(int $year): array
    {
        $year = max(2000, min(2100, $year));

        $companyRows = LiburPerusahaan::where('is_active', true)
            ->where('tanggal', 'like', $year . '%')
            ->get();

        $national = $this->nationalHolidayService->forYear($year);
        $nationalItems = $national['items'];

        $additionalWorking = $this->collectAdditionalWorkingDates($companyRows);
        $companyHolidayDates = $this->collectCompanyHolidayDates($companyRows);

        $nationalHolidayDates = array_map(fn ($item) => $item['tanggal'], $nationalItems);
        $nationalAfterOverrides = array_values(array_filter(
            $nationalHolidayDates,
            fn ($date) => !in_array($date, $additionalWorking, true)
        ));

        $holidayDates = array_values(array_unique(array_merge($nationalAfterOverrides, $companyHolidayDates)));
        sort($holidayDates);

        $events = $this->mergeDetailEvents($nationalItems, $companyRows);

        return [
            'data' => $companyRows,
            'calendar' => [
                'events' => $events,
                'holiday_dates' => $holidayDates,
                'additional_working_dates' => array_values(array_unique($additionalWorking)),
                'national_source' => $national['source'],
                'national_available' => $nationalItems !== [],
            ],
        ];
    }

    private function collectAdditionalWorkingDates(Collection $companyRows): array
    {
        $dates = [];

        foreach ($companyRows as $row) {
            $tipe = (string) ($row->tipe ?? '');
            if ($tipe === 'cuti_bersama_tapi_masuk') {
                $d = $this->normalizeDate($row->tanggal ?? null);
                if ($d !== null) {
                    $dates[] = $d;
                }
            }

            if ($this->isReplacementHolidayType($tipe)) {
                $replacement = $this->normalizeDate($row->tgl_ganti ?? null);
                if ($replacement !== null) {
                    $dates[] = $replacement;
                }
            }
        }

        sort($dates);

        return $dates;
    }

    private function collectCompanyHolidayDates(Collection $companyRows): array
    {
        $dates = [];

        foreach ($companyRows as $row) {
            $tipe = (string) ($row->tipe ?? '');
            if (!$this->isCompanyOffDayType($tipe)) {
                continue;
            }
            $d = $this->normalizeDate($row->tanggal ?? null);
            if ($d !== null) {
                $dates[] = $d;
            }
        }

        return $dates;
    }

    private function isCompanyOffDayType(string $tipe): bool
    {
        return in_array($tipe, [
            'cuti_bersama',
            'libur_pengganti',
            'Penggantian',
        ], true);
    }

    private function isReplacementHolidayType(string $tipe): bool
    {
        return in_array($tipe, ['libur_pengganti', 'Penggantian'], true);
    }

    /**
     * @param list<array{tanggal: string, keterangan: string}> $nationalItems
     * @return list<array{tanggal: string, keterangan: string}>
     */
    private function mergeDetailEvents(array $nationalItems, Collection $companyRows): array
    {
        $merged = [];

        foreach ($nationalItems as $item) {
            $merged[$item['tanggal']] = [
                'tanggal' => $item['tanggal'],
                'keterangan' => $item['keterangan'],
            ];
        }

        foreach ($companyRows as $row) {
            $date = $this->normalizeDate($row->tanggal ?? null);
            $label = trim((string) ($row->keterangan ?? ''));
            if ($date === null || $label === '') {
                continue;
            }

            $tipe = (string) ($row->tipe ?? '');
            if ($this->isReplacementHolidayType($tipe) && !empty($row->tgl_ganti)) {
                $ganti = $this->normalizeDate($row->tgl_ganti);
                if ($ganti !== null) {
                    $label .= ' (pengganti ' . $ganti . ')';
                }
            }

            if (isset($merged[$date])) {
                $merged[$date]['keterangan'] = $merged[$date]['keterangan'] . ' (' . $label . ')';
            } else {
                $merged[$date] = ['tanggal' => $date, 'keterangan' => $label];
            }
        }

        $out = array_values($merged);
        usort($out, fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

        return $out;
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tanggal libur (nasional + perusahaan, setelah override masuk) dalam rentang inclusive.
     *
     * @return list<string> Y-m-d
     */
    public function holidayDatesBetween(string $fromYmd, string $toYmd): array
    {
        try {
            $from = Carbon::parse($fromYmd);
            $to = Carbon::parse($toYmd);
        } catch (\Throwable $e) {
            return [];
        }

        if ($to->lt($from)) {
            return [];
        }

        $dates = [];
        for ($year = (int) $from->year; $year <= (int) $to->year; $year++) {
            $payload = $this->forYear($year);
            foreach ($payload['calendar']['holiday_dates'] as $d) {
                if ($d >= $fromYmd && $d <= $toYmd) {
                    $dates[] = $d;
                }
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }
}
