<?php

namespace App\Support;

class RateLimitIpWhitelist
{
    public static function isWhitelisted(string $clientIp): bool
    {
        $whitelist = config('ratelimit.whitelist_ips', []);

        foreach ($whitelist as $entry) {
            if ($entry === '') {
                continue;
            }

            if (self::ipMatches($clientIp, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cocokkan IP dengan entri whitelist (exact atau wildcard, mis. 10.88.*).
     */
    public static function ipMatches(string $clientIp, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return $clientIp === $pattern;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

        return (bool) preg_match($regex, $clientIp);
    }
}
