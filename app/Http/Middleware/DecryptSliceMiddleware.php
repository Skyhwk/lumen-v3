<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Services\Crypto;

class DecryptSliceMiddleware
{
    protected $crypto;

    public function __construct(Crypto $crypto)
    {
        $this->crypto = $crypto;
    }

    public function handle($request, Closure $next)
    {
        // Retrieve the X-Slice header
        $encryptedSlice = $request->header('X-Slice');
        if (!$encryptedSlice) {
            return response()->json(['message' => 'X-Slice header missing'], 400);
        }

        try {
            $decryptedSlice = $this->crypto->decryptSlice($encryptedSlice);
            $slice = json_decode($decryptedSlice, true);

            if (!is_array($slice) || empty($slice['controller']) || empty($slice['function'])) {
                return response()->json(['message' => 'Invalid request format'], 400);
            }

            $request->headers->set('X-Slice', json_encode($slice));
        } catch (Exception $e) {
            $message = $e->getMessage();
            $status = strpos($message, 'expired') !== false ? 401 : 400;

            return response()->json(['message' => 'Decryption failed: ' . $message], $status);
        }

        return $next($request);
    }
}
