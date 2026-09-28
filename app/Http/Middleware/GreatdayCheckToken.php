<?php

namespace App\Http\Middleware;

use App\Cache\GreatdayTokenCacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Auth greatday — terpisah dari portal (CheckToken pakai header "token" + User portal).
 * Greatday: Authorization Bearer dan/atau X-Greatday-Token, DB intilab_apps.user_token.
 */
class GreatdayCheckToken
{
    /** @var GreatdayTokenCacheService */
    private $tokenCacheService;

    public function __construct(GreatdayTokenCacheService $tokenCacheService)
    {
        $this->tokenCacheService = $tokenCacheService;
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $this->resolveGreatdayToken($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided',
            ], 403);
        }

        try {
            $tokenData = $this->tokenCacheService->getUserTokenWithCache($token);

            if (!$tokenData || !$tokenData->isActive()) {
                return response()->json([
                    'message' => 'Token is invalid or expired',
                ], 403);
            }

            $karyawan = $tokenData->karyawan;

            if (!$karyawan || !$karyawan->is_active) {
                return response()->json(['message' => 'User is inactive'], 403);
            }

            $request->attributes->set('greatday_karyawan', $karyawan);
            $request->attributes->set('greatday_token', $tokenData);

            return $next($request);
        } catch (\Throwable $th) {
            Log::error('GreatdayCheckToken error', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'token_hash' => hash('sha256', $token),
            ]);

            $message = config('app.debug')
                ? 'Token validation failed: ' . $th->getMessage()
                : 'Token validation failed';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }
    }

    /**
     * Hanya sumber token greatday — jangan baca header "token" (portal ISL).
     */
    private function resolveGreatdayToken(Request $request): ?string
    {
        $fromHeader = trim((string) $request->header('X-Greatday-Token', ''));
        if ($fromHeader !== '') {
            return $fromHeader;
        }

        $bearer = $request->bearerToken();
        if ($bearer) {
            return $bearer;
        }

        return null;
    }
}
