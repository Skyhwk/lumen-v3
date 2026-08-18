<?php

namespace App\Cache;

use App\Models\UserToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TokenCacheService
{
    const CACHE_PREFIX = 'user_token:';
    const USER_CACHE_PREFIX = 'user:';

    /**
     * Ambil token user — cache Redis kecuali mode impersonate.
     *
     * @param string $token
     * @return UserToken|null
     */
    public function getUserTokenWithCache($token)
    {
        $userToken = $this->fetchTokenFromDatabase($token);

        if (!$userToken) {
            return null;
        }

        // Selalu pakai data DB sebagai sumber kebenaran auth.
        // Cache hanya dipakai untuk warm-up; hindari stale object Redis
        // yang bisa memicu token dianggap expired padahal masih aktif di DB.
        if (!$this->shouldBypassCache($userToken)) {
            Cache::put(
                $this->getTokenCacheKey($token),
                $userToken,
                $this->getCacheTtl()
            );
        }

        return $userToken;
    }

    /**
     * Invalidate cache token tunggal.
     */
    public function invalidateTokenCache($token)
    {
        if (!$token) {
            return;
        }

        Cache::forget($this->getTokenCacheKey($token));

        // Log::info('Token cache invalidated', [
        //     'token_hash' => hash('sha256', $token),
        // ]);
    }

    /**
     * Invalidate cache data user.
     */
    public function invalidateUserCache($userId)
    {
        if (!$userId) {
            return;
        }

        Cache::forget(self::USER_CACHE_PREFIX . $userId);

        Log::info('User cache invalidated', ['user_id' => $userId]);
    }

    /**
     * Invalidate cache user + token spesifik.
     */
    public function invalidateAllUserCache($userId, $token = null)
    {
        $this->invalidateUserCache($userId);

        if ($token) {
            $this->invalidateTokenCache($token);
        }
    }

    /**
     * Invalidate cache untuk semua token aktif milik user.
     */
    public function invalidateAllTokensForUser($userId)
    {
        if (!$userId) {
            return;
        }

        $tokens = UserToken::where('user_id', $userId)
            ->where('is_expired', false)
            ->pluck('token');

        foreach ($tokens as $token) {
            $this->invalidateTokenCache($token);
        }

        $this->invalidateUserCache($userId);
    }

    /**
     * Warm up cache untuk token yang sering digunakan.
     *
     * @param array $tokens
     */
    public function warmUpCache(array $tokens)
    {
        foreach ($tokens as $token) {
            $this->invalidateTokenCache($token);
            $this->getUserTokenWithCache($token);
        }

        Log::info('Cache warmed up dan diperbarui', [
            'token_count' => count($tokens),
            'action' => 'refresh_cache',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCacheStats()
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'default_ttl' => $this->getCacheTtl(),
        ];
    }

    private function fetchTokenFromDatabase($token)
    {
        return UserToken::with(['user.karyawan'])
            ->where('token', $token)
            ->first();
    }

    private function shouldBypassCache(UserToken $userToken)
    {
        return (bool) $userToken->is_impersonate
            || !empty($userToken->impersonator_user_id);
    }

    private function getTokenCacheKey($token)
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }

    private function getCacheTtl()
    {
        return (int) config('auth.token_cache_ttl', 3600);
    }
}
