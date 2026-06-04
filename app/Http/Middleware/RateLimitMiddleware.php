<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use App\Support\RateLimitIpWhitelist;
use Closure;
use Illuminate\Http\Request;

class RateLimitMiddleware
{
    /**
     * @var RateLimitService
     */
    private $rateLimitService;

    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!config('ratelimit.enabled', true)) {
            return $next($request);
        }

        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        if ($this->shouldSkipGlobalLimit($request)) {
            return $next($request);
        }

        $clientIp = $request->ip() ?? 'unknown';
        if (RateLimitIpWhitelist::isWhitelisted($clientIp)) {
            return $next($request);
        }

        $rule = $this->resolveRule($request);
        $identifier = $rule['prefix'] . ':ip:' . $clientIp;
        $result = $this->rateLimitService->check(
            $identifier,
            $rule['max_attempts'],
            $rule['decay_seconds']
        );

        if (!$result['allowed']) {
            return $this->buildTooManyRequestsResponse($result);
        }

        $response = $next($request);

        return $this->attachRateLimitHeaders($response, $result);
    }

    /**
     * Route ini sudah dilindungi UserRateLimitMiddleware (per user, setelah auth).
     */
    private function shouldSkipGlobalLimit(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return in_array($path, ['api/route', 'api/mobile'], true);
    }

    /**
     * @return array{max_attempts: int, decay_seconds: int, prefix: string}
     */
    private function resolveRule(Request $request): array
    {
        $path = trim($request->path(), '/');
        $rules = config('ratelimit.rules', []);

        foreach ($rules['groups'] ?? [] as $group) {
            foreach ($group['paths'] as $pattern) {
                if ($this->pathMatches($path, $pattern)) {
                    return [
                        'max_attempts' => (int) $group['max_attempts'],
                        'decay_seconds' => (int) $group['decay_minutes'] * 60,
                        'prefix' => $group['name'] ?? 'group',
                    ];
                }
            }
        }

        $default = $rules['default'] ?? ['max_attempts' => 120, 'decay_minutes' => 1];

        return [
            'max_attempts' => (int) $default['max_attempts'],
            'decay_seconds' => (int) $default['decay_minutes'] * 60,
            'prefix' => 'default',
        ];
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        if (strpos($pattern, '*') !== false) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

            return (bool) preg_match($regex, $path);
        }

        return $path === $pattern;
    }

    /**
     * @param array{allowed: bool, remaining: int, retry_after: int, limit: int} $result
     */
    private function buildTooManyRequestsResponse(array $result)
    {
        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $result['retry_after'],
        ], 429)
            ->header('Retry-After', (string) $result['retry_after'])
            ->header('X-RateLimit-Limit', (string) $result['limit'])
            ->header('X-RateLimit-Remaining', '0');
    }

    /**
     * @param mixed $response
     * @param array{allowed: bool, remaining: int, retry_after: int, limit: int} $result
     * @return mixed
     */
    private function attachRateLimitHeaders($response, array $result)
    {
        if (!method_exists($response, 'header')) {
            return $response;
        }

        return $response
            ->header('X-RateLimit-Limit', (string) $result['limit'])
            ->header('X-RateLimit-Remaining', (string) $result['remaining']);
    }
}
