<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class Utf8Sanitizer
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        // Log::info([[$response]]);
        // Hanya proses kalau response berupa JSON
        if (method_exists($response, 'getData')) {
            $data = $response->getData(true);

            // Pastikan $data adalah array sebelum diproses dengan array_walk_recursive
            if (is_array($data)) {
                // <<< PENTING: tambahin "use ($request)" biar variabel kebawa ke dalam closure
                array_walk_recursive($data, function (&$item, $key) use ($request) {
                    if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
                        // Log string bermasalah
                        Log::warning('UTF-8 Malformed detected', [
                            'route' => $request->path(),   // sekarang dijamin ada
                            'field' => $key,
                            'value_sample' => substr($item, 0, 100),
                        ]);

                        // Convert biar tetep aman
                        $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
                    }
                });
            } else {
                if(is_string($data) && !mb_check_encoding($data, 'UTF-8')) {
                    Log::warning('UTF-8 Malformed detected', [
                        'route' => $request->path(),   // sekarang dijamin ada
                        'field' => $key,
                        'value_sample' => substr($item, 0, 100),
                    ]);
                    $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
                }
            }

            return response()->json(
                $data,
                $response->status(),
                $response->headers->all(),
                JSON_UNESCAPED_UNICODE
            );
        }

        // Log::info([$response]);
        return $response;
    }
}