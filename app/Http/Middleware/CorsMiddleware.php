<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    /* public function handle($request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json([], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization, token, X-Slice');
        }

        $response = $next($request);

        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization, token, X-Slice');

        return $response;
    } */
   public function handle($request, Closure $next)
    {
        // JIKA MENGGUNAKAN PENDEKATAN 1 (NGINX HANDLE CORS):
        // COMMENT OUT ATAU HAPUS MIDDLEWARE INI
        // return $next($request);

        // JIKA MENGGUNAKAN PENDEKATAN 2 (LARAVEL HANDLE CORS):
        // GUNAKAN CODE DI BAWAH INI

        $headers = [
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
            'Access-Control-Allow-Headers'     => 'Content-Type, X-Requested-With, Authorization, token, X-Slice, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400'
        ];

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json('{"method":"OPTIONS"}', 200, $headers);
        }

        $response = $next($request);

        // Add CORS headers to response
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }

}
