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
            // Decrypt the X-Slice header
            $decryptedSlice = $this->crypto->decrypt($encryptedSlice);
            $slice = json_decode($decryptedSlice, true);

            if (!$slice || $slice === null) {
                return response()->json(['message' => 'Invalid request format'], 400);
            }

            // Replace the encrypted X-Slice header with the decrypted slice
            $request->headers->set('X-Slice', json_encode($slice));
        } catch (Exception $e) {
            return response()->json(['message' => 'Decryption failed: ' . $e->getMessage()], 500);
        }

        return $next($request);
    }
}
