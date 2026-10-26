<?php

namespace App\Support\Greatday;

use Carbon\Carbon;

class FormSubmissionDates
{
    public const TZ = 'Asia/Jakarta';

    /**
     * @return string|null Pesan error atau null jika valid
     */
    public static function validateRangeNotBackdated(?string $startDate, ?string $endDate): ?string
    {
        if (!$startDate || !$endDate) {
            return null;
        }

        $today = Carbon::now(self::TZ)->startOfDay();
        $start = Carbon::parse($startDate, self::TZ)->startOfDay();
        $end = Carbon::parse($endDate, self::TZ)->startOfDay();

        if ($start->lt($today)) {
            return 'Tanggal mulai tidak boleh sebelum hari ini.';
        }

        if ($end->lt($today)) {
            return 'Tanggal selesai tidak boleh sebelum hari ini.';
        }

        if ($end->lt($start)) {
            return 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }

        return null;
    }

    /**
     * PHL: start = tanggal masuk di hari libur, end = tanggal pengganti (1 hari kerja).
     *
     * @return string|null Pesan error atau null jika valid
     */
    public static function validatePhlDates(?string $workedDate, ?string $replacementDate): ?string
    {
        if (!$workedDate || !$replacementDate) {
            return 'Tanggal masuk di hari libur dan tanggal pengganti wajib diisi.';
        }

        $today = Carbon::now(self::TZ)->startOfDay();
        $worked = Carbon::parse($workedDate, self::TZ)->startOfDay();
        $replacement = Carbon::parse($replacementDate, self::TZ)->startOfDay();

        if ($worked->gt($today)) {
            return 'Tanggal masuk di hari libur tidak boleh setelah hari ini.';
        }

        if ($replacement->lt($today)) {
            return 'Tanggal pengganti tidak boleh sebelum hari ini.';
        }

        if ($replacement->lte($worked)) {
            return 'Tanggal pengganti harus setelah tanggal masuk di hari libur.';
        }

        return null;
    }
}
