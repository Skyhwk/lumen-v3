<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserToken;
use App\Models\RequestLog;
use App\Models\Menu;
use App\Models\MenuFdl;
use Auth;
use Validator;
use Exception;
use Laravel\Lumen\Routing\Controller as BaseController;
use Carbon\Carbon;

class AuthController extends BaseController
{
    public function getToken(Request $request)
    {
        try{
            $rules = [
                'identity' => 'required|string',
                'password' => 'required|string',
                'active'    => '1',
            ];
    
            $messages = [
                'identity.required' => 'Username or email is required',
                'password.required' => 'Password is required',
            ];
    
            $validator = Validator::make($request->all(), $rules, $messages);
            if($validator->fails()){
                return response()->json(['message' => 'Login Failed (Username or email or Password is required)', 'status' => '401'], 401);
            }
            
            $identity  = $request->identity;
            $fieldName = filter_var($identity, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            
            $user = User::where("$fieldName", $identity)->where('is_active', true)->first();
            if (!$user) {
                return response()->json(['message' => 'Login Failed (User not Found)', 'status' => '401'], 401);
            }
            
            $isValidPassword = Hash::check($request->password, $user->password);
            
            if (!$isValidPassword) {
                return response()->json(['message' => 'Login Failed (Wrong Password)', 'status' => '401'], 401);
            }

            UserToken::where('user_id', $user->id)
                ->where('is_logged_in', true)
                ->update(['is_logged_in' => false, 'is_expired' => true]);

            $token = bin2hex(random_bytes(40)).strtotime(DATE('Y-m-d H:i:s'));
            $userToken = new UserToken;
            $userToken->user_id           = $user->id;
            $userToken->token             = $token;
            $userToken->create_date      = DATE('Y-m-d H:i:s');
            $userToken->expired           = DATE("Y-m-d H:i:s", strtotime("+7 day", strtotime($userToken->create_date)));
            $userToken->is_logged_in      = true;
            $userToken->type              = 'private';
            $userToken->save();

            $response = response()->json([
                'token' => $token,
                'created_at' => DATE('Y-m-d H:i:s'),
                'expired_at' => $userToken->expired,
                'created_at_js' => date("M d Y H:i:s", strtotime(DATE('Y-m-d H:i:s'))),
                'expired_at_js' => date("M d Y H:i:s", strtotime($userToken->expired)),
            ]);

            $this->logRequest($request, $response->getContent(), $user->karyawan->nama_lengkap);

            return $response;
        } catch (Exception $e) {
            return response()->json(['message' => 'Login Failed (Internal Server Error)'], 500);
        }
    }

    public function checkToken(Request $request)
    {
        $token = $request->header('token');

        if (!$token) {
            $this->logRequest($request, 'Token not provided');
            return response()->json(['message' => 'Token not provided'], 430);
        }

        $userToken = UserToken::where('token', $token)->first();

        if (!$userToken || $userToken->is_expired) {
            $this->logRequest($request, 'Token is invalid or expired');
            return response()->json(['message' => 'Token is invalid or expired.!'], 430);
        }

        $karyawan = $userToken->karyawan;
        
        if (!$karyawan) {
            $this->logRequest($request, 'User is inactive');
            return response()->json(['message' => 'User is inactive'], 430);
        }
        
        $akses = $userToken->akses;
        if($akses!=null){
            $keys = $akses->akses;
        } else {
            $keys = [];
        }

        // id = 1 ==> Direktur
        // id = 127 ==> Administrator
        // id = 152 ==> Patah
       
        $menuList =  MenuFdl::where('is_active', 1)->where('title', '!=', 'Lainnya')->get();
        
        $wiseList = MenuFdl::where('is_active', 1)->where('is_wiseList', 1)->get();

        $strukture_menu = Menu::where('is_active', true)->orderBy('menu', 'asc')->get();
        $response = response()->json([
            'dept' => $karyawan->department,
            'image' => $karyawan->image,
            'email' => $karyawan->email,
            'access' => $keys,
            'strukture_menu' => $strukture_menu,
            'name' => $karyawan->nama_lengkap,
            'pos' => $karyawan->jabatan,
            'role' => $karyawan->role,
            'join' => $karyawan->join_date,
            'impersonate' => ($karyawan->id == 1 || $karyawan->id == 127 || $karyawan->id == 152) || $userToken->is_impersonate,
            'message' => 'Token Valid',
            'user_id' => $karyawan->id,
            'sip_username' => $userToken->webphone ? $userToken->webphone->sip_username : null,
            'sip_password' => $userToken->webphone ? $userToken->webphone->sip_password : null,
            'fdl_menu' => $menuList,
            'fdl_wise_list' => $wiseList,
        ]);

        $this->logRequest($request, $response->getContent(), $karyawan->nama_lengkap);

        return $response;
    }

    private function logRequest($request, $result, $name_req = null)
    {
        if (!empty($request->all())) {
            $userAgent = $request->header('User-Agent');
            $platform = $this->getPlatformFromUserAgent($userAgent);
    
            RequestLog::create([
                'name_req' => $name_req != null ? $name_req: $request->header('token'),
                'date_req' => DATE('Y-m-d H:i:s'),
                'data_req' => json_encode($request->all()),
                'user_agent' => $request->header('User-Agent'),
                'result' => $result,
                'path_info' => $request->path(),
                'ip' => $request->ip(),
                'platform' => $platform
            ]);
        }
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
        } elseif (preg_match('/Thunder Client/i', $userAgent)) {
            return 'Thunder Client';
        } else {
            return 'Other';
        }
    }
}
