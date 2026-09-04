<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Services\Crypto;
use Illuminate\Support\Facades\Log;

class DecryptSliceMiddleware
{
    protected $crypto;

    public function __construct(Crypto $crypto)
    {
        $this->crypto = $crypto;
    }

    public function handle($request, Closure $next)
    {
        $encryptedSlice = $request->header('X-Slice');
        if (!$encryptedSlice) {
            return response()->json(['message' => 'X-Slice header missing'], 400);
        }

        try {
            if (is_array($encryptedSlice)) {
                throw new Exception('X-Slice header is duplicated (array)');
            }

            $decryptedSlice = $this->crypto->decryptSlice($encryptedSlice);
            $slice = json_decode($decryptedSlice, true);

            if (!is_array($slice) || empty($slice['controller']) || empty($slice['function'])) {
                return response()->json(['message' => 'Invalid request format'], 400);
            }

            $request->headers->set('X-Slice', json_encode($slice));

            if (filter_var(env('SLICE_DEBUG_LOG', false), FILTER_VALIDATE_BOOLEAN)) {
                $this->logSliceEvent('success', $request, $encryptedSlice);
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $status = strpos($message, 'expired') !== false ? 401 : 400;

            $this->logSliceEvent('failed', $request, $encryptedSlice, $message);

            return response()->json(['message' => 'Decryption failed: ' . $message], $status);
        }

        return $next($request);
    }

    private function logSliceEvent(string $outcome, $request, $encryptedSlice, ?string $errorMessage = null): void
    {
        $slice = is_string($encryptedSlice) ? $encryptedSlice : json_encode($encryptedSlice);
        $payload = $slice === false ? '' : $slice;
        $base64Part = '';

        if (strpos($payload, 'v1.') === 0) {
            $base64Part = substr($payload, 3);
        }

        Log::channel('slice_debug')->info('slice_decrypt_' . $outcome, [
            'outcome' => $outcome,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->header('User-Agent'), 0, 120),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
            'php_sapi' => PHP_SAPI,
            'header_type' => is_array($encryptedSlice) ? 'array' : gettype($encryptedSlice),
            'slice_length' => strlen($payload),
            'has_v1_prefix' => strpos($payload, 'v1.') === 0,
            'has_plus' => strpos($payload, '+') !== false,
            'has_slash' => strpos($payload, '/') !== false,
            'has_space' => strpos($payload, ' ') !== false,
            'has_comma' => strpos($payload, ',') !== false,
            'base64_strict_ok' => $base64Part !== '' ? (base64_decode($base64Part, true) !== false) : false,
            'base64_decoded_length' => $base64Part !== '' && base64_decode($base64Part, true) !== false
                ? strlen(base64_decode($base64Part, true))
                : null,
            'slice_secret_length' => strlen((string) env('SLICE_SECRET', '')),
            'slice_config_secret_length' => strlen((string) config('slice.secret', '')),
            'app_env' => app()->environment(),
            'preview_start' => substr($payload, 0, 15),
            'preview_end' => strlen($payload) > 10 ? substr($payload, -10) : $payload,
            'error' => $errorMessage,
        ]);
    }
}
