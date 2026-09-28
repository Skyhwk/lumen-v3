<?php

namespace App\Cache;

use App\Models\Gd\GdUserToken;
use App\Models\Greatday\UserToken;

class GreatdayTokenCacheService
{
    /**
     * Token greatday: intilab_apps.user_token atau gd_user_token (produksi).
     */
    public function getUserTokenWithCache(string $token)
    {
        if (config('greatday.use_produksi_gd_auth', false)) {
            return GdUserToken::with('karyawan')
                ->where('token', $token)
                ->first();
        }

        return UserToken::with('karyawan')
            ->where('token', $token)
            ->first();
    }
}
