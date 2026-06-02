<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;

class UserRateLimitMiddleware
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

        $user = $request->attributes->get('user');
        if (!$user) {
            return $next($request);
        }

        $config = config('ratelimit.authenticated', [
            'max_attempts' => 300,
            'decay_minutes' => 1,
        ]);

        $identifier = 'user:' . $user->id;
        $result = $this->rateLimitService->check(
            $identifier,
            (int) $config['max_attempts'],
            (int) $config['decay_minutes'] * 60
        );

        if (!$result['allowed']) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $result['retry_after'],
            ], 429)
                ->header('Retry-After', (string) $result['retry_after'])
                ->header('X-RateLimit-Limit', (string) $result['limit'])
                ->header('X-RateLimit-Remaining', '0');
        }

        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', (string) $result['limit']);
            $response->header('X-RateLimit-Remaining', (string) $result['remaining']);
        }

        return $response;
    }
}
