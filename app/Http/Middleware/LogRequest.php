<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\RequestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LogRequest
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response->getStatusCode() != 500 && !$this->isExcludedResponse($response)) {
            if (!empty($request->all())) {
                $userAgent = $request->header('User-Agent');
                $platform = $this->getPlatformFromUserAgent($userAgent);

                if ($request->attributes->has('user')) {
                    $user = $request->attributes->get('user');
                    if(isset($user->karyawan) && $user->karyawan!=null){
                        $name_req = $user->karyawan->nama_lengkap;
                    } else {
                        $name_req = $user->email;
                    }
                    
                } else {
                    $name_req = $request->header('token');
                }

                $data_req = [
                    'name_req' => $name_req,
                    'date_req' => date('Y-m-d H:i:s'),
                    'data_req' => json_encode($request->all()),
                    'user_agent' => $request->header('User-Agent'),
                    'result' => $response->getContent(),
                    'path_info' => $request->path(),
                    'ip' => $request->ip(),
                    'platform' => $platform
                ];

                // Log::channel('log_request')->info('new request : ', $data_req);

                // RequestLog::create([
                //     'name_req' => $name_req,
                //     'date_req' => date('Y-m-d H:i:s'),
                //     'data_req' => json_encode($request->all()),
                //     'user_agent' => $request->header('User-Agent'),
                //     'result' => $response->getContent(),
                //     'path_info' => $request->path(),
                //     'ip' => $request->ip(),
                //     'platform' => $platform
                // ]);
            }
        }

        return $response;
    }

    private function getPlatformFromUserAgent($userAgent)
    {
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'Mac';
        } elseif (preg_match('/windows|win32/i', $userAgent)) {
            return 'Windows';
        } elseif (preg_match('/android/i', $userAgent)) {
            return 'Android';
        } elseif (preg_match('/iphone/i', $userAgent)) {
            return 'iOS';
        } elseif (preg_match('/thunder client/i', $userAgent)) {
            return 'Thunder Client';
        } elseif (preg_match('/postman/i', $userAgent)) {
            return 'PostMan';
        } else {
            return 'Other';
        }
    }

    private function isExcludedResponse($response)
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (isset($data['draw'])) {
                return true;
            }
        }
        return false;
    }
}
