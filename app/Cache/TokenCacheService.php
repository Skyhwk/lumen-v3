<?php
namespace App\Cache;

use App\Models\UserToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TokenCacheService
{
    const CACHE_PREFIX = 'user_token:';
    const CACHE_TTL = 3600; // 1 jam
    const USER_CACHE_PREFIX = 'user:';
    const USER_CACHE_TTL = 1800; // 30 menit

    /**
     * Get user token dengan cache
     *
     * @param string $token
     * @return array|null
     */
    public function getUserTokenWithCache($token)
    {
        // $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
        
        // $cachedToken = Cache::get($cacheKey);
        // if ($cachedToken) {
        //     // Log::info('Cache hit untuk token', ['token' => $token, 'cachedToken' => $cachedToken]);
        //     return $cachedToken;
        // }

        // Log::info('Cache miss untuk token, query ke database', ['token' => $token]);
        $userToken = UserToken::where('token', $token)->first();
        
        if (!$userToken) {
            return null;
        }

        // Cache::put($cacheKey, $userToken, self::CACHE_TTL);
        return $userToken;
    }

    /**
     * Invalidate token cache
     *
     * @param string $token
     * @return void
     */
    public function invalidateTokenCache($token)
    {
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
        Cache::forget($cacheKey);
        
        Log::info('Token cache invalidated', ['token_hash' => hash('sha256', $token)]);
    }

    /**
     * Invalidate user cache
     *
     * @param int $userId
     * @return void
     */
    public function invalidateUserCache($userId)
    {
        $cacheKey = self::USER_CACHE_PREFIX . $userId;
        Cache::forget($cacheKey);
        
        Log::info('User cache invalidated', ['user_id' => $userId]);
    }

    /**
     * Invalidate semua cache untuk user (token + user data)
     *
     * @param int $userId
     * @param string|null $token
     * @return void
     */
    public function invalidateAllUserCache($userId, $token = null)
    {
        $this->invalidateUserCache($userId);
        
        if ($token) {
            $this->invalidateTokenCache($token);
        }
    }

    /**
     * Warm up cache untuk token yang sering digunakan
     *
     * @param array $tokens
     * @return void
     */
    public function warmUpCache(array $tokens)
    {
        // Hapus semua cache lama dengan prefix yang sesuai
        foreach ($tokens as $token) {
            $cacheKey = self::CACHE_PREFIX . hash('sha256', $token);
            Cache::forget($cacheKey);
        }

        // Generate cache baru
        foreach ($tokens as $token) {
            $this->getUserTokenWithCache($token);
        }
        
        Log::info('Cache warmed up dan diperbarui', [
            'token_count' => count($tokens),
            'action' => 'refresh_cache'
        ]);
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getCacheStats()
    {
        // Basic cache stats untuk monitoring
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'default_ttl' => self::CACHE_TTL,
        ];
    }
}