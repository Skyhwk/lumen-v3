<?php

namespace App\Services\Greatday;

use App\Models\Gd\GdUser;
use App\Models\Gd\GdUserToken;
use App\Models\Greatday\User;
use App\Models\Greatday\UserToken;
use Carbon\Carbon;

class GreatdayAuthService
{
    public function usesProduksiAuth(): bool
    {
        return (bool) config('greatday.use_produksi_gd_auth', false);
    }

    /** Tulis token ke apps + produksi saat migrasi M7. */
    public function dualWriteAuth(): bool
    {
        if ($this->usesProduksiAuth()) {
            return false;
        }

        return (bool) config('greatday.dual_write_gd_auth', true);
    }

    public function findLoginUser(string $fieldName, string $credential): ?object
    {
        if ($this->usesProduksiAuth()) {
            $user = GdUser::with('karyawan')->where($fieldName, $credential)->where('is_active', true)->first();

            return $user && $user->karyawan ? $user : null;
        }

        $user = User::with('karyawan')->where($fieldName, $credential)->where('is_active', true)->first();

        return $user && $user->karyawan ? $user : null;
    }

    public function karyawanIdFromAccount(object $account): int
    {
        if ($account instanceof GdUser) {
            return (int) $account->karyawan_id;
        }

        return (int) $account->user_id;
    }

    public function findAccountByKaryawanId(int $karyawanId): ?object
    {
        if ($this->usesProduksiAuth()) {
            return GdUser::where('karyawan_id', $karyawanId)->where('is_active', true)->first();
        }

        return User::where('user_id', $karyawanId)->where('is_active', true)->first();
    }

    public function expireActiveTokens(int $karyawanId): void
    {
        if ($this->usesProduksiAuth() || $this->dualWriteAuth()) {
            GdUserToken::where('karyawan_id', $karyawanId)
                ->where('is_logged_in', true)
                ->update(['is_logged_in' => false, 'is_expired' => true]);
        }

        if (!$this->usesProduksiAuth()) {
            UserToken::where('user_id', $karyawanId)
                ->where('is_logged_in', true)
                ->update(['is_logged_in' => false, 'is_expired' => true]);
        }
    }

    public function saveSessionToken(int $karyawanId, string $token, Carbon $createDate, Carbon $expired): void
    {
        $payload = [
            'token' => $token,
            'create_date' => $createDate,
            'expired' => $expired,
            'is_logged_in' => true,
            'is_expired' => false,
            'type' => 'private',
        ];

        if ($this->usesProduksiAuth() || $this->dualWriteAuth()) {
            GdUserToken::create(array_merge($payload, ['karyawan_id' => $karyawanId]));
        }

        if (!$this->usesProduksiAuth()) {
            $legacy = new UserToken();
            $legacy->user_id = $karyawanId;
            foreach ($payload as $key => $value) {
                $legacy->{$key} = $value;
            }
            $legacy->save();
        }
    }

    public function expireToken(string $token): void
    {
        if ($this->usesProduksiAuth() || $this->dualWriteAuth()) {
            GdUserToken::where('token', $token)
                ->where('is_logged_in', true)
                ->update(['is_logged_in' => false, 'is_expired' => true]);
        }

        if (!$this->usesProduksiAuth()) {
            UserToken::where('token', $token)
                ->where('is_logged_in', true)
                ->update(['is_logged_in' => false, 'is_expired' => true]);
        }
    }
}
