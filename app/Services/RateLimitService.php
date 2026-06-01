<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RateLimitService
{
    const CACHE_PREFIX = 'rate_limit:';

    /**
     * @return array{allowed: bool, remaining: int, retry_after: int, limit: int}
     */
    public function check(string $identifier, int $maxAttempts, int $decaySeconds): array
    {
        $window = (int) floor(time() / max(1, $decaySeconds));
        $cacheKey = self::CACHE_PREFIX . $identifier . ':' . $window;

        $attempts = Cache::increment($cacheKey);
        if ($attempts === 1) {
            Cache::put($cacheKey, 1, $decaySeconds + 10);
        }

        $retryAfter = $decaySeconds - (time() % max(1, $decaySeconds));

        return [
            'allowed' => $attempts <= $maxAttempts,
            'remaining' => max(0, $maxAttempts - $attempts),
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
        ];
    }
}
