<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return self::applyCorsHeaders($request, response('', 204));
        }

        // dd()/dump()/exit — respons tidak lewat applyCorsHeaders; kirim CORS di awal request.
        self::sendEarlyCorsHeaders($request);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $response = app(ExceptionHandler::class)->render($request, $e);
        }

        return self::applyCorsHeaders($request, $response);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     */
    public static function applyCorsHeaders($request, Response $response): Response
    {
        $values = self::resolveCorsValues($request);

        if (!self::headerAlreadySent('Access-Control-Allow-Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $values['allow_origin']);
            $response->headers->set('Access-Control-Allow-Methods', $values['allow_methods']);
            $response->headers->set('Access-Control-Allow-Headers', $values['allow_headers']);
            $response->headers->set('Access-Control-Max-Age', $values['max_age']);
        }

        return $response;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    private static function sendEarlyCorsHeaders($request): void
    {
        if (headers_sent()) {
            return;
        }

        $values = self::resolveCorsValues($request);

        header('Access-Control-Allow-Origin: ' . $values['allow_origin']);
        header('Access-Control-Allow-Methods: ' . $values['allow_methods']);
        header('Access-Control-Allow-Headers: ' . $values['allow_headers']);
        header('Access-Control-Max-Age: ' . $values['max_age']);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{allow_origin: string, allow_methods: string, allow_headers: string, max_age: string}
     */
    private static function resolveCorsValues($request): array
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowOrigin = '*';

        if ($origin && count($allowedOrigins) > 0) {
            if (in_array($origin, $allowedOrigins, true)) {
                $allowOrigin = $origin;
            }
        } elseif ($origin && count($allowedOrigins) === 0) {
            $allowOrigin = $origin;
        }

        $requestedHeaders = trim((string) $request->headers->get('Access-Control-Request-Headers'));
        $allowHeaders = $requestedHeaders !== ''
            ? $requestedHeaders
            : (string) config('cors.allowed_headers');

        return [
            'allow_origin' => $allowOrigin,
            'allow_methods' => (string) config('cors.allowed_methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS'),
            'allow_headers' => $allowHeaders,
            'max_age' => (string) config('cors.max_age', 86400),
        ];
    }

    private static function headerAlreadySent(string $name): bool
    {
        $prefix = strtolower($name) . ':';
        foreach (headers_list() as $line) {
            if (stripos($line, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
