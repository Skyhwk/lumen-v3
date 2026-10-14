<?php

namespace App\Services\Greatday;

use App\Models\Gd\GdUser;
use Illuminate\Support\Facades\Hash;

class SlipGajiPinService
{
    private static function normalizePin($pin): string
    {
        $pin = trim((string) $pin);

        if (!preg_match('/^\d{6}$/', $pin)) {
            throw new \InvalidArgumentException('PIN harus 6 digit angka.');
        }

        return $pin;
    }

    private static function isBcryptHash(?string $hash): bool
    {
        return is_string($hash) && preg_match('/^\$2[ayb]\$\d{2}\$.{53}$/', $hash) === 1;
    }

    /** Sama dengan GreatdayAuthService: karyawan_id dulu, fallback id (= legacy user_id). */
    private static function userForKaryawan($karyawanId): ?GdUser
    {
        $id = (int) $karyawanId;

        return GdUser::where('karyawan_id', $id)->first()
            ?? GdUser::where('id', $id)->first();
    }

    private static function pinHashForKaryawan($karyawanId): ?string
    {
        $user = self::userForKaryawan($karyawanId);
        if (!$user) {
            return null;
        }

        $hash = $user->pin_user;

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public static function hasPin($karyawanId)
    {
        $hash = self::pinHashForKaryawan($karyawanId);
        if ($hash === null) {
            return false;
        }

        if (!self::isBcryptHash($hash)) {
            self::removePin($karyawanId);

            return false;
        }

        return true;
    }

    public static function setPin($karyawanId, $pin)
    {
        $user = self::userForKaryawan($karyawanId);
        if (!$user) {
            throw new \RuntimeException('Akun Greatday (gd_user) tidak ditemukan untuk karyawan ini.');
        }

        $user->pin_user = Hash::make(self::normalizePin($pin));
        $user->save();
    }

    public static function verifyPin($karyawanId, $pin)
    {
        $hash = self::pinHashForKaryawan($karyawanId);

        if ($hash === null) {
            return true;
        }

        if (!self::isBcryptHash($hash)) {
            self::removePin($karyawanId);

            return false;
        }

        try {
            $normalized = self::normalizePin($pin);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return Hash::check($normalized, $hash);
    }

    public static function removePin($karyawanId)
    {
        $user = self::userForKaryawan($karyawanId);
        if (!$user) {
            return;
        }

        $user->pin_user = null;
        $user->save();
    }
}
