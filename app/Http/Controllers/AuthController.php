<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserToken;
use App\Models\RequestLog;
use App\Models\MasterKaryawan;
use App\Models\Menu;
use App\Models\MenuFdl;
use Auth;
use Validator;
use Exception;
use Laravel\Lumen\Routing\Controller as BaseController;
use Carbon\Carbon;
use App\Models\DashboardComponent;
use App\Models\SetAksesDashboard;
use App\Models\DashboardUserOrder;
use Illuminate\Support\Facades\Schema;
use App\Services\AuthTokenService;
use App\Services\DeviceParser;
use App\Services\PasswordResetService;

class AuthController extends BaseController
{
    /** @var AuthTokenService */
    private $authTokenService;

    /** @var PasswordResetService */
    private $passwordResetService;

    public function __construct(AuthTokenService $authTokenService, PasswordResetService $passwordResetService)
    {
        $this->authTokenService = $authTokenService;
        $this->passwordResetService = $passwordResetService;
    }

    public function getToken(Request $request)
    {
        try{
            $rules = [
                'identity' => 'required|string',
                'password' => 'required|string',
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

            $confirmDisplace = filter_var($request->input('confirm_displace', false), FILTER_VALIDATE_BOOLEAN);
            $rememberMe = filter_var($request->input('remember_me', false), FILTER_VALIDATE_BOOLEAN);
            $sessionMeta = $this->authTokenService->buildSessionMetaFromRequest($request);
            $sessionMeta['token_ttl_days'] = $this->authTokenService->resolveLoginTokenTtlDays($rememberMe);
            $sessionResult = $this->authTokenService->attemptCreateSession(
                $user->id,
                $sessionMeta,
                $confirmDisplace
            );

            if ($sessionResult['status'] === 'limit_reached') {
                $oldest = $sessionResult['oldest'];
                $displacedSummary = $oldest ? DeviceParser::formatSessionSummary($oldest) : null;

                return response()->json([
                    'message' => 'SESSION_LIMIT_REACHED',
                    'current_device' => $sessionResult['current_device'],
                    'active_sessions' => $sessionResult['active_sessions'],
                    'displaced_session' => $displacedSummary,
                ], 409);
            }

            $userToken = $sessionResult['token'];
            $displaced = $sessionResult['displaced'] ?? null;

            $responseData = [
                'token' => $userToken->token,
                'created_at' => $userToken->create_date,
                'expired_at' => $userToken->expired,
                'created_at_js' => date('M d Y H:i:s', strtotime($userToken->create_date)),
                'expired_at_js' => date('M d Y H:i:s', strtotime($userToken->expired)),
                'device' => $sessionMeta['platform'],
            ];

            if ($displaced) {
                $displacedSummary = DeviceParser::formatSessionSummary($displaced);
                $responseData['session_notice'] = [
                    'displaced_device' => $displacedSummary['platform'],
                    'message' => sprintf(
                        'Sesi %s telah logout otomatis karena batas %d perangkat.',
                        $displacedSummary['platform'],
                        $this->authTokenService->getMaxActiveSessions()
                    ),
                ];
            }

            $response = response()->json($responseData);

            $logName = $user->karyawan ? $user->karyawan->nama_lengkap : $user->username;
            $this->logRequest($request, $response->getContent(), $logName);

            return $response;
        } catch (Exception $e) {
            return response()->json(['message' => 'Login Failed (Internal Server Error)'], 500);
        }
    }

    public function logout(Request $request)
    {
        $token = $request->header('token');

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 400);
        }

        $userToken = UserToken::where('token', $token)->first();

        if ($userToken && $userToken->isActive()) {
            $this->authTokenService->expireToken($userToken);
        }

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ], [
            'email.required' => 'Email perusahaan wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            $result = $this->passwordResetService->sendOtp(
                $request->email,
                $request->ip()
            );

            $status = $result['status'] ?? 200;

            return response()->json([
                'message' => $result['message'],
            ], $status);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal mengirim OTP. Silakan coba lagi.',
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6',
        ], [
            'email.required' => 'Email perusahaan wajib diisi.',
            'otp.required' => 'OTP wajib diisi.',
            'otp.size' => 'OTP harus 6 digit.',
            'new_password.required' => 'Password baru wajib diisi.',
            'confirm_password.required' => 'Konfirmasi password wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            $result = $this->passwordResetService->resetPassword(
                $request->email,
                $request->otp,
                $request->new_password,
                $request->confirm_password
            );

            $status = $result['status'] ?? ($result['success'] ? 200 : 400);

            return response()->json([
                'message' => $result['message'],
            ], $status);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal reset password. Silakan coba lagi.',
            ], 500);
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

        if (!$userToken) {
            $this->logRequest($request, 'Token not found');
            return response()->json(['message' => 'Token tidak ditemukan'], 430);
        }

        if (!$userToken->isActive()) {
            $this->logRequest($request, 'Token is invalid or expired');
            return response()->json(['message' => 'Sesi telah berakhir, silakan login ulang'], 430);
        }

        $this->authTokenService->touchSession($userToken);

        $karyawan = $userToken->karyawan;
        
        if (!$karyawan) {
            $this->logRequest($request, 'User is inactive');
            return response()->json(['message' => 'User is inactive'], 430);
        }
        
        $copy_paste = [];
        $akses = $userToken->akses;
        if($akses!=null){
            $keys = $akses->akses;
            $copy_paste = [
                "copy_access" => $akses->copy_access,
                "paste_access" => $akses->paste_access,
            ];
        } else {
            $keys = [];
        }

        $keys = $this->filterNonEmailAccess($keys);

        // id = 1 ==> Direktur
        // id = 127 ==> Administrator
        // id = 152 ==> Patah
        $menuList = MenuFdl::where('is_active', 1)
            ->where('title', '!=', 'Lainnya')
            ->where(function($q) use ($karyawan) {
                $q->whereNull('access_restricted')
                ->orWhereJsonContains('access_restricted', $karyawan->id);
            })
            ->get();
        
        $wiseList = MenuFdl::where('is_active', 1)->where('is_wiseList', 1)->get();

        $strukture_menu = Menu::where('is_active', true)
            ->whereRaw('LOWER(menu) != ?', ['email'])
            ->orderBy('menu', 'asc')
            ->get();

        $dashboardOwner =  DashboardComponent::where(function($query) use ($karyawan) {
            $query->where('owner_id', $karyawan->id)
                  ->orWhereRaw("FIND_IN_SET(?, owner_id)", [$karyawan->id]);
        })->where('is_active', 1)->get();

         $dashboardOwner->transform(function($component) use ($karyawan) {
            $akses = null;
            if (Schema::hasColumn('set_akses_dashboard', 'id_dashboard_component')) {
                $akses = SetAksesDashboard::whereNull('deleted_at')
                    ->where('id_dashboard_component', $component->id)
                    ->first();
            }

            $visibility = $akses ? ($akses->user_visibility ?? []) : [];
            $component->dashboard_component_id = $component->id;
            $component->user_list = [];
            $component->user_visibility_status = array_key_exists((string) $karyawan->id, $visibility)
                ? (bool) $visibility[(string) $karyawan->id]
                : true;

            return $component;
        });

        $dashboardAccess = SetAksesDashboard::whereJsonContains(
                'user_list',
                $karyawan->nama_lengkap
            )->whereNull('deleted_at')->get();

        $dashboardAccess->transform(function($item) use ($karyawan) {
            $component = null;

            if (!empty($item->id_dashboard_component)) {
                $component = DashboardComponent::where('id', $item->id_dashboard_component)
                    ->where('is_active', 1)
                    ->first();
            }

            if (!$component) {
                $component = DashboardComponent::where('nama_dashboard', $item->nama_dashboard)
                    ->where('is_active', 1)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            $visibility = $item->user_visibility ?? [];
            $item->dashboard_component_id = $component ? $component->id : ($item->id_dashboard_component ?? $item->id);
            $item->nama_komponen = $component ? $component->nama_komponen : null;
            $item->nama_dashboard = $component ? $component->nama_dashboard : $item->nama_dashboard;
            $item->owner_id = $component ? $component->owner_id : ($item->owner_id ?? null);
            $item->user_visibility_status = array_key_exists((string) $karyawan->id, $visibility)
                ? (bool) $visibility[(string) $karyawan->id]
                : true;

            return $item;
        });

        $dashboard = $dashboardOwner->merge($dashboardAccess)
            ->filter(function($item) {
                return !empty($item->nama_komponen) && $item->user_visibility_status !== false;
            })
            ->unique('dashboard_component_id')
            ->values();

        $dashboard = $this->applyDashboardOrder($dashboard, $karyawan->id);

        $impersonatorUserId = $userToken->impersonator_user_id;
        $canImpersonate = $this->canUseImpersonateFeature($userToken);
        $isImpersonating = (bool) $userToken->is_impersonate;
        $impersonatorName = null;

        if ($isImpersonating && $impersonatorUserId) {
            $impersonatorKaryawan = MasterKaryawan::where('user_id', $impersonatorUserId)->first();
            $impersonatorName = $impersonatorKaryawan ? $impersonatorKaryawan->nama_lengkap : null;
        } elseif ($isImpersonating && $canImpersonate) {
            $actorUserId = $this->resolveActorUserIdForImpersonation($userToken);
            $impersonatorUserId = $actorUserId;
            $impersonatorKaryawan = MasterKaryawan::where('user_id', $actorUserId)->first();
            $impersonatorName = $impersonatorKaryawan ? $impersonatorKaryawan->nama_lengkap : null;
        }
        
        $response = response()->json([
            'dept' => $karyawan->department,
            'image' => $karyawan->image,
            'email' => $karyawan->email,
            'access' => $keys,
            'strukture_menu' => $strukture_menu,
            'dashboard' => $dashboard,
            'name' => $karyawan->nama_lengkap,
            'pos' => $karyawan->jabatan,
            'grade' => $karyawan->grade,
            'role' => $karyawan->role,
            'join' => $karyawan->join_date,
            'impersonate' => $canImpersonate,
            'can_impersonate' => $canImpersonate,
            'is_impersonating' => $isImpersonating,
            'impersonator_name' => $impersonatorName,
            'impersonator_user_id' => $impersonatorUserId,
            'portal_user_id' => $userToken->user_id,
            'message' => 'Token Valid',
            'user_id' => $karyawan->id,
            'copy_paste' => $copy_paste,
            'sip_username' => $userToken->webphone ? $userToken->webphone->sip_username : null,
            'sip_password' => $userToken->webphone ? $userToken->webphone->sip_password : null,
            'fdl_menu' => $menuList,
            'fdl_wise_list' => $wiseList,
        ]);

        $this->logRequest($request, $response->getContent(), $karyawan->nama_lengkap);

        return $response;
    }

    private function filterNonEmailAccess($access): array
    {
        if (!is_array($access)) {
            return [];
        }

        return array_values(array_filter($access, function ($item) {
            if (!is_array($item)) {
                return true;
            }

            $parent = strtolower((string) ($item['parent'] ?? ''));
            $name = strtolower((string) ($item['name'] ?? ''));
            $path = (string) ($item['path'] ?? '');

            if ($name === 'email') {
                return false;
            }

            if ($parent === 'email' || strpos($parent, 'email/') === 0) {
                return false;
            }

            if ($path !== '' && strpos($path, '/email') === 0) {
                return false;
            }

            return true;
        }));
    }

    private function applyDashboardOrder($dashboard, $userId)
    {
        if (!Schema::hasTable('dashboard_user_orders')) {
            return $dashboard->sortBy(function ($item) {
                $dashboardId = (int) ($item->dashboard_component_id ?? $item->id_dashboard_component ?? $item->id);

                return sprintf('%06d-%06d', $this->getDefaultDashboardOrder($item), $dashboardId);
            })->values()->map(function ($item, $index) {
                $item->sort_order = $index;

                return $item;
            });
        }

        $savedOrder = DashboardUserOrder::where('user_id', $userId)->first();
        $order = $savedOrder ? ($savedOrder->dashboard_order ?? []) : [];
        $orderMap = array_flip(array_map('intval', $order));

        return $dashboard->sortBy(function ($item) use ($orderMap) {
            $dashboardId = (int) ($item->dashboard_component_id ?? $item->id_dashboard_component ?? $item->id);
            $savedOrderIndex = array_key_exists($dashboardId, $orderMap) ? $orderMap[$dashboardId] : null;
            $defaultOrderIndex = $this->getDefaultDashboardOrder($item);

            return sprintf('%06d-%06d', $savedOrderIndex ?? $defaultOrderIndex, $dashboardId);
        })->values()->map(function ($item, $index) {
            $item->sort_order = $index;

            return $item;
        });
    }

    private function getDefaultDashboardOrder($item)
    {
        $defaultOrder = [
            'DashboardSales' => 1,
            'DashboardAdmSampling' => 2,
            'DashboardStaffTc' => 3,
            'DashboardAnalist' => 4,
            'DashboardHRD' => 5,
            'DashboardPaymentPerformance' => 6,
        ];

        return $defaultOrder[$item->nama_komponen ?? ''] ?? 999999;
    }

    private function canUseImpersonateFeature(UserToken $userToken)
    {
        if (!empty($userToken->impersonator_user_id)) {
            return $this->userCanImpersonate((int) $userToken->impersonator_user_id);
        }

        return $this->userCanImpersonate((int) $userToken->user_id);
    }

    private function userCanImpersonate($userId)
    {
        $karyawan = MasterKaryawan::where('user_id', $userId)->first();

        if (!$karyawan) {
            return false;
        }

        return in_array((int) $karyawan->id, [1, 127, 152], true);
    }

    private function resolveActorUserIdForImpersonation(UserToken $userToken)
    {
        if (!empty($userToken->impersonator_user_id)) {
            return (int) $userToken->impersonator_user_id;
        }

        return (int) $userToken->user_id;
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
