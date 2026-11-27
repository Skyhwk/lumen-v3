<?php

namespace App\Http\Middleware;

use Closure;

class InternalPpiToken
{
    public function handle($request, Closure $next)
    {
        $expected = (string) env('PPI_INTERNAL_TOKEN', '');
        $given = (string) ($request->header('token') ?: $request->header('X-Internal-Token'));

        if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
            return response()->json([
                'success' => false,
                'message' => 'Token validation failed',
            ], 430);
        }

        return $next($request);
    }
}
