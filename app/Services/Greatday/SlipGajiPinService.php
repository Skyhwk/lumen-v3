<?php

namespace App\Services\Greatday;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class SlipGajiPinService
{
    private static function cacheKey($karyawanId)
    {
        return 'slip_gaji_pin:' . $karyawanId;
    }

    public static function hasPin($karyawanId)
    {
        return !empty(Cache::get(self::cacheKey($karyawanId)));
    }

    public static function setPin($karyawanId, $pin)
    {
        Cache::forever(self::cacheKey($karyawanId), Hash::make($pin));
    }

    public static function verifyPin($karyawanId, $pin)
    {
        $hash = Cache::get(self::cacheKey($karyawanId));

        if (empty($hash)) {
            return true;
        }

        return Hash::check($pin, $hash);
    }

    public static function removePin($karyawanId)
    {
        Cache::forget(self::cacheKey($karyawanId));
    }
}
