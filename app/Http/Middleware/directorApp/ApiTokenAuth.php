<?php

namespace App\Http\Middleware\directorApp;

use Closure;
use App\Services\directorApp\TokenManager;

class ApiTokenAuth
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        $user = app(TokenManager::class)->getUserByToken($token);

        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        return $next($request);
    }
}
