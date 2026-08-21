<?php

namespace App\Services;

use App\Models\LiburPerusahaan;
use App\Models\NationalHoliday;
use Carbon\Carbon;

class CustomerServiceBusinessHours
{
    public const WORK_START_HOUR = 8;
    public const WORK_END_HOUR = 17;
    public const TIMEZONE = 'Asia/Jakarta';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function isWeekend(Carbon $date): bool
    {
        return $date->isSaturday() || $date->isSunday();
    }

    public static function isNationalHoliday(Carbon $date): bool
    {
        return NationalHoliday::whereDate('date', $date->toDateString())->exists();
    }

    public static function isCompanyHoliday(Carbon $date): bool
    {
        return LiburPerusahaan::whereDate('tanggal', $date->toDateString())->exists();
    }

    public static function isHoliday(Carbon $date): bool
    {
        return self::isNationalHoliday($date) || self::isCompanyHoliday($date);
    }

    public static function isWorkingDay(Carbon $date): bool
    {
        return $date->isWeekday() && !self::isHoliday($date);
    }

    public static function isWithinWorkingHours(?Carbon $now = null): bool
    {
        $now = $now ?? self::now();

        if (!self::isWorkingDay($now)) {
            return false;
        }

        $hour = (int) $now->format('G');

        return $hour >= self::WORK_START_HOUR && $hour < self::WORK_END_HOUR;
    }

    public static function nextWorkingDay(Carbon $from): Carbon
    {
        $cursor = $from->copy()->startOfDay()->addDay();
        $guard = 0;

        while ((!$cursor->isWeekday() || self::isHoliday($cursor)) && $guard < 14) {
            $cursor->addDay();
            $guard++;
        }

        return $cursor;
    }

    public static function buildResponseTimeHint(?Carbon $now = null): string
    {
        $now = $now ?? self::now();

        if (self::isWithinWorkingHours($now)) {
            return 'Tim kami akan segera merespons pesan Anda pada jam kerja (Senin–Jumat, pukul 08.00–17.00 WIB).';
        }

        if ($now->isSaturday() || $now->isSunday()) {
            return 'Saat ini di luar jam kerja kami (Senin–Jumat, 08.00–17.00 WIB). Pesan Anda akan kami respon pada hari Senin di jam kerja.';
        }

        if (self::isHoliday($now)) {
            $next = self::nextWorkingDay($now);

            if ($next->isMonday() && $now->copy()->addDay()->isMonday()) {
                return 'Saat ini libur. Pesan Anda akan kami respon pada hari Senin di jam kerja.';
            }

            return 'Saat ini libur. Pesan Anda akan kami respon pada ' . $next->translatedFormat('l, d F Y') . ' di jam kerja.';
        }

        if ((int) $now->format('G') >= self::WORK_END_HOUR) {
            $next = self::nextWorkingDay($now);

            if ($next->isSameDay($now->copy()->addDay())) {
                return 'Saat ini di luar jam kerja kami (Senin–Jumat, 08.00–17.00 WIB). Pesan Anda akan kami respon besok pada jam kerja.';
            }

            if ($next->isMonday()) {
                return 'Saat ini di luar jam kerja kami (Senin–Jumat, 08.00–17.00 WIB). Pesan Anda akan kami respon pada hari Senin di jam kerja.';
            }

            return 'Saat ini di luar jam kerja kami (Senin–Jumat, 08.00–17.00 WIB). Pesan Anda akan kami respon pada ' . $next->translatedFormat('l, d F Y') . ' di jam kerja.';
        }

        return 'Saat ini di luar jam kerja kami (Senin–Jumat, 08.00–17.00 WIB). Pesan Anda akan kami respon hari ini pada jam kerja.';
    }

    public static function buildWelcomeMessage(?Carbon $now = null): string
    {
        $intro = 'Terima kasih telah menghubungi Layanan Customer Service kami. Pesan Anda sudah kami terima dan sedang menunggu antrian tim kami.';

        return $intro . "\n\n" . self::buildResponseTimeHint($now);
    }
}
