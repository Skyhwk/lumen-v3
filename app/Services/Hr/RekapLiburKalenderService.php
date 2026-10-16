<?php

namespace App\Services\Hr;

use App\Models\RekapLiburKalender;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class RekapLiburKalenderService
{
    /** @var array<string, array<string, true>> */
    private $workingDayIndexCache = [];

    /**
     * Index tanggal hari kerja (Y-m-d) dari rekap_libur_kalender per tahun.
     *
     * @param  int|string  $year
     * @return array<string, true>
     */
    public function workingDayIndexForYear($year)
    {
        $year = (string) $year;
        if (array_key_exists($year, $this->workingDayIndexCache)) {
            return $this->workingDayIndexCache[$year];
        }

        $record = RekapLiburKalender::where('tahun', $year)
            ->where('is_active', true)
            ->first();

        if (!$record || empty($record->tanggal)) {
            $this->workingDayIndexCache[$year] = [];

            return $this->workingDayIndexCache[$year];
        }

        $decoded = json_decode($record->tanggal, true);
        if (!is_array($decoded)) {
            $this->workingDayIndexCache[$year] = [];

            return $this->workingDayIndexCache[$year];
        }

        $index = [];
        foreach ($decoded as $dates) {
            if (!is_array($dates)) {
                continue;
            }
            foreach ($dates as $date) {
                if ($date === null || $date === '') {
                    continue;
                }
                try {
                    $index[Carbon::parse($date)->format('Y-m-d')] = true;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        $this->workingDayIndexCache[$year] = $index;

        return $index;
    }

    public function isWorkingDay($ymd)
    {
        try {
            $parsed = Carbon::parse($ymd);
        } catch (\Throwable $e) {
            return false;
        }

        $key = $parsed->format('Y-m-d');
        $index = $this->workingDayIndexForYear($parsed->year);

        if ($index === []) {
            return $parsed->isWeekday();
        }

        return isset($index[$key]);
    }

    public function countWorkingDaysBetween($start, $end)
    {
        if (!$start) {
            return 0;
        }

        $end = $end ?: $start;

        try {
            $from = Carbon::parse($start)->startOfDay();
            $to = Carbon::parse($end)->startOfDay();
        } catch (\Throwable $e) {
            return 0;
        }

        if ($to->lt($from)) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        $count = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            if ($this->isWorkingDay($day->format('Y-m-d'))) {
                $count++;
            }
        }

        return $count;
    }

    public function countCalendarDaysBetween($start, $end)
    {
        if (!$start) {
            return 0;
        }

        $end = $end ?: $start;

        try {
            $from = Carbon::parse($start)->startOfDay();
            $to = Carbon::parse($end)->startOfDay();
        } catch (\Throwable $e) {
            return 0;
        }

        if ($to->lt($from)) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        return $from->diffInDays($to) + 1;
    }
}
