<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class Utf8Sanitizer
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Hanya proses kalau response berupa JSON
        if (method_exists($response, 'getData')) {
            $data = $response->getData(true);

            if (is_array($data)) {
                array_walk_recursive($data, function (&$item, $key) use ($request) {
                    if (is_string($item)) {
                        // Cek encoding
                        if (!mb_check_encoding($item, 'UTF-8')) {
                            Log::warning('UTF-8 Malformed detected', [
                                'route' => $request->path(),
                                'field' => $key,
                                'value_sample' => substr($item, 0, 100),
                            ]);
                            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
                        }

                        // ✅ Decode HTML entities
                        // contoh: &amp; -> &, &quot; -> "
                        $item = html_entity_decode($item, ENT_QUOTES, 'UTF-8');
                    }
                });
            } else {
                if (is_string($data)) {
                    if (!mb_check_encoding($data, 'UTF-8')) {
                        Log::warning('UTF-8 Malformed detected', [
                            'route' => $request->path(),
                            'field' => null,
                            'value_sample' => substr($data, 0, 100),
                        ]);
                        $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
                    }

                    // ✅ Decode HTML entities
                    $data = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
                }
            }

            return response()->json(
                $data,
                $response->status(),
                $response->headers->all(),
                JSON_UNESCAPED_UNICODE
            );
        }

        return $response;
    }
}
