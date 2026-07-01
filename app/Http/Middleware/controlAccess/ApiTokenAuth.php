<?php

namespace App\Http\Middleware\controlAccess;

use Closure;
use App\Services\controlAccess\TokenManager;

class ApiTokenAuth
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        $session = app(TokenManager::class)->getUserByToken($token);

        if (!$session) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->attributes->add(['controlAccessUser' => $session]);

        return $next($request);
    }
}
