<?php
//
//namespace App\Http\Middleware;
//
//use Closure;
//use App\Models\UserToken;
//use Illuminate\Support\Facades\Log;
//
//class CheckToken
//{
//    public function handle($request, Closure $next)
//    {
//        try {
//            $token = $request->header('token');
//    
//            if (!$token) {
//                return response()->json(['message' => 'Token not provided'], 403);
//            }
//    
//            $userToken = UserToken::where('token', $token)->first();
//            
//            if (!$userToken || $userToken->is_expired) {
//                return response()->json(['message' => 'Token is invalid or expired.!'], 403);
//            }
//    
//            $user = $userToken->user;
//    
//            if (!$user) {
//                return response()->json(['message' => 'User is inactive'], 403);
//            }
//    
//            $request->attributes->add(['user' => $user]);
//    
//            return $next($request);
//        } catch (\Throwable $th) {
//            Log::error($th);
//            return response()->json(['message' => 'Token is invalid or expired.!'], 403);
//        }
//    }
//}
//

namespace App\Http\Middleware;

use Closure;
use App\Cache\TokenCacheService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckToken
{
    /**
     * @var TokenCacheService
     */
    private $tokenCacheService;

    public function __construct(TokenCacheService $tokenCacheService)
    {
        $this->tokenCacheService = $tokenCacheService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            $token = $request->header('token');
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 403);
            }

            // Get token data dari cache
            $tokenData = $this->tokenCacheService->getUserTokenWithCache($token);
            
            if (!$tokenData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token is invalid or expired'
                ], 403);
            }

            if (!$tokenData || $tokenData->is_expired) {
                return response()->json(['message' => 'Token is invalid or expired.!'], 403);
            }
    
            $user = $tokenData->user;
    
            if (!$user) {
                return response()->json(['message' => 'User is inactive'], 403);
            }

            $request->attributes->add(['user' => $user]);
            
            return $next($request);

        } catch (\Throwable $th) {
            Log::error('CheckToken middleware error', [
                'error' => $th->getMessage(),
                'token_hash' => $token ? hash('sha256', $token) : null,
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Token validation failed'
            ], 403);
        }
    }
}