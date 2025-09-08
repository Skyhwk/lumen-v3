<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class Utf8Sanitizer
{
    public function handle($request, Closure $next)
    {
        // === 1. Sanitasi Request Input ===
        $input = $request->all();
        array_walk_recursive($input, function (&$item, $key) {
            if (is_string($item)) {
                $item = $this->sanitizeString($item, $key, 'request');
            }
        });
        $request->replace($input);

        // === 2. Lanjut ke controller / middleware berikutnya ===
        $response = $next($request);

        // === 3. Jika response JSON, sanitasi datanya ===
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            array_walk_recursive($data, function (&$item, $key) {
                if (is_string($item)) {
                    $item = $this->sanitizeString($item, $key, 'response');
                }
            });

            return response()->json(
                $data,
                $response->status(),
                $response->headers->all(),
                JSON_UNESCAPED_UNICODE
            );
        }

        return $response;
    }

    private function sanitizeString($item, $key, $context)
    {
        // Pastikan UTF-8
        if (!mb_check_encoding($item, 'UTF-8')) {
            $encoding = mb_detect_encoding($item, ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252'], true) ?: 'UTF-8';
            Log::channel('utf8')->warning("Malformed UTF-8 in {$context}", [
                'field' => $key,
                'value_sample' => substr($item, 0, 100),
                'detected_encoding' => $encoding,
            ]);
            $item = mb_convert_encoding($item, 'UTF-8', $encoding);
        }

        // Decode HTML entities berulang sampai stabil
        $prev = null;
        while ($item !== $prev) {
            $prev = $item;
            $item = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $item;
    }
}
