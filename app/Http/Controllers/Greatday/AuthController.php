<?php

namespace App\Http\Controllers\Greatday;

use App\Models\Greatday\OTP;
use App\Models\Greatday\User;
use App\Models\Greatday\RequestLog;
use App\Models\Greatday\TemporaryForgot;
use App\Models\Greatday\TemporaryRegister;
use App\Models\MasterKaryawan;
use App\Services\Greatday\GreatdayAuthService;
use App\Services\Greatday\GreatdayMailer;
use App\Support\Greatday\GreatdayAppData;
use App\Support\Greatday\HrdPayroll;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Lumen\Routing\Controller;

class AuthController extends Controller
{
    /** Kompatibel greatday: POST body credential, password, fcm_token */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'credential' => 'required|string',
                'password' => 'required|string',
                'fcm_token' => 'nullable|string',
            ], [
                'credential.required' => 'Username or email is required',
                'password.required' => 'Password is required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Login Failed (Username or email or Password is required)',
                    'status' => '401',
                ], 401);
            }

            $credential = $request->credential;
            $fieldName = filter_var($credential, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            /** @var GreatdayAuthService $authStore */
            $authStore = app(GreatdayAuthService::class);
            $user = $authStore->findLoginUser($fieldName, $credential);

            if (!$user || !$user->karyawan || !$user->karyawan->is_active) {
                return response()->json(['message' => 'Login Failed (User not Found)', 'status' => '401'], 401);
            }

            $karyawanId = $authStore->karyawanIdFromAccount($user);

            $isValidPassword = Hash::check($request->password, $user->password);

            if (!$isValidPassword) {
                $hasOTP = OTP::where('user_id', $karyawanId)
                    ->orderByDesc('created_at')
                    ->first();

                if (!$hasOTP || $hasOTP->is_expired) {
                    return response()->json(['message' => 'Login Failed (Wrong Password)', 'status' => '401'], 401);
                }

                if ($hasOTP->created_at < Carbon::now()->subMinutes(60)) {
                    OTP::where('user_id', $karyawanId)->update(['is_expired' => true]);
                    return response()->json(['message' => 'Login Failed (OTP Expired)', 'status' => '401'], 401);
                }

                if ($request->password != $hasOTP->password) {
                    return response()->json(['message' => 'Login Failed (Wrong OTP)', 'status' => '401'], 401);
                }
            }

            $authStore->expireActiveTokens($karyawanId);

            $token = bin2hex(random_bytes(40)) . strtotime(Carbon::now());
            $ttlDays = (int) config('greatday.login_token_ttl_days', 7);
            $createDate = Carbon::now();
            $authStore->saveSessionToken($karyawanId, $token, $createDate, $createDate->copy()->addDays($ttlDays));

            if ($request->fcm_token) {
                $this->handleFcmToken($request->fcm_token, $karyawanId);
            }

            $response = response()->json([
                'message' => 'Logged in successfully',
                'data' => [
                    'user' => $this->buildSessionUserPayload($user->karyawan, $user),
                    'token' => $token,
                    'menus' => collect(GreatdayAppData::menusForUser($karyawanId))->values()->toArray(),
                    'permissions' => GreatdayAppData::permissionsForUser($karyawanId),
                ],
            ], 200);

            $this->logRequest($request, $response->getContent(), $user->karyawan->nama_lengkap);

            return $response;
        } catch (Exception $e) {
            return response()->json(['message' => 'Login Failed (Internal Server Error)'], 500);
        }
    }

    /** POST cektoken — alias refresh session (compat frontend Internal) */
    public function checkToken(Request $request)
    {
        return $this->me($request);
    }

    /** GET profile/me — refresh session di greatday */
    public function me(Request $request)
    {
        $karyawan = $request->attributes->get('greatday_karyawan');
        if (!$karyawan) {
            return response()->json(['message' => 'User is inactive'], 403);
        }

        $authStore = app(GreatdayAuthService::class);
        $account = $authStore->findAccountByKaryawanId($karyawan->id);
        if (!$account) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $profile = $this->buildSessionUserPayload($karyawan, $account);

        return response()->json([
            'data' => [
                'user' => $profile,
                'menus' => collect(GreatdayAppData::menusForUser($karyawan->id))->values()->toArray(),
                'permissions' => GreatdayAppData::permissionsForUser($karyawan->id),
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        if ($token) {
            app(GreatdayAuthService::class)->expireToken($token);
        }

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function forgotPassword(Request $request)
    {
        $credential = $request->credential;

        TemporaryForgot::where('email', $credential)
            ->where(function ($query) {
                $query->where('is_used', true)
                    ->orWhere('expired_at', '<', Carbon::now());
            })
            ->delete();

        $checkTemporaryActive = TemporaryForgot::where('email', $credential)
            ->where('is_used', false)
            ->where('expired_at', '>', Carbon::now())
            ->first();

        if ($checkTemporaryActive) {
            return response()->json([
                'message' => 'A password reset link has already been sent to your email. Please check your inbox (and spam folder).',
            ], 422);
        }

        DB::connection(config('greatday.apps_connection', 'intilab_apps'))->beginTransaction();

        try {
            $isPegawai = MasterKaryawan::where('email', $credential)->where('is_active', true)->first();
            if (!$isPegawai) {
                return response()->json([
                    'message' => 'This company email is not registered. Please contact HR.',
                ], 422);
            }

            $checkUser = User::where('email', $credential)->where('is_active', true)->first();
            if (!$checkUser) {
                return response()->json(['message' => 'This email is not registered. Please Register First.'], 404);
            }

            $token = bin2hex(random_bytes(40)) . strtotime(Carbon::now());

            $temporary = TemporaryForgot::updateOrCreate(
                ['email' => $checkUser->email],
                [
                    'user_id' => $isPegawai->id,
                    'token' => $token,
                    'expired_at' => Carbon::now()->addDays(1)->format('Y-m-d H:i:s'),
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'is_used' => false,
                ]
            );

            $encodedToken = base64_encode($token);
            $portalUrl = config('greatday.portal_attendance_url');
            $verificationUrl = $portalUrl . '/forgot-password?token=' . urlencode($encodedToken);

            GreatdayMailer::sendForgotPassword($isPegawai->email, [
                'username' => $isPegawai->nama_lengkap,
                'email' => $isPegawai->email,
                'verificationUrl' => $verificationUrl,
                'expiredAt' => Carbon::parse($temporary->expired_at)->format('d-m-Y H:i:s'),
            ]);

            DB::connection(config('greatday.apps_connection', 'intilab_apps'))->commit();

            return response()->json(['message' => 'Send Forgot Password Success'], 200);
        } catch (Exception $e) {
            DB::connection(config('greatday.apps_connection', 'intilab_apps'))->rollBack();

            return response()->json([
                'message' => 'Registration failed, please contact IT Programmer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->messages(),
            ], 422);
        }

        $checkTemporaryActive = TemporaryRegister::where('email', $request->email)->where('is_used', false)->first();

        if ($checkTemporaryActive) {
            if (Carbon::parse($checkTemporaryActive->expired_at)->isPast()) {
                $checkTemporaryActive->delete();
            } else {
                return response()->json([
                    'message' => 'A verification link has already been sent to your email. Please check your inbox (and spam folder).',
                ], 422);
            }
        }

        DB::connection(config('greatday.apps_connection', 'intilab_apps'))->beginTransaction();

        try {
            $checkUser = User::where('email', $request->email)->where('is_active', true)->first();
            if ($checkUser) {
                return response()->json([
                    'message' => 'This email is already registered. Please log in using this email.',
                ], 422);
            }

            $isPegawai = MasterKaryawan::where('email', $request->email)->where('is_active', true)->first();
            if (!$isPegawai) {
                return response()->json([
                    'message' => 'This company email is not registered. Please contact HR.',
                ], 422);
            }

            $token = bin2hex(random_bytes(40)) . strtotime(Carbon::now());

            $temporary = new TemporaryRegister();
            $temporary->user_id = $isPegawai->id;
            $temporary->email = $isPegawai->email;
            $temporary->token = $token;
            $temporary->expired_at = Carbon::now()->addDays(1)->format('Y-m-d H:i:s');
            $temporary->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $temporary->is_used = false;
            $temporary->save();

            $encodedToken = base64_encode($token);
            $portalUrl = config('greatday.portal_attendance_url');
            $verificationUrl = $portalUrl . '/setup-password?token=' . urlencode($encodedToken);

            GreatdayMailer::sendRegistration($isPegawai->email, [
                'username' => $isPegawai->nama_lengkap,
                'email' => $isPegawai->email,
                'verificationUrl' => $verificationUrl,
                'expiredAt' => Carbon::parse($temporary->expired_at)->format('d-m-Y H:i:s'),
            ]);

            DB::connection(config('greatday.apps_connection', 'intilab_apps'))->commit();

            return response()->json([
                'message' => 'Registration successful',
            ], 201);
        } catch (\Throwable $th) {
            DB::connection(config('greatday.apps_connection', 'intilab_apps'))->rollBack();

            return response()->json([
                'message' => 'Registration failed, please contact IT Programmer',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    private function handleFcmToken($token, $user_id)
    {
        $fcmToken = GreatdayAppData::fcmTokenQuery()->firstOrNew(['user_id' => $user_id]);
        $fcmToken->fcm_token = $token;
        if (!$fcmToken->exists) {
            $fcmToken->created_at = Carbon::now();
        }
        $fcmToken->updated_at = Carbon::now();
        $fcmToken->save();
    }

    private function buildSessionUserPayload(MasterKaryawan $karyawan, object $account): array
    {
        return [
            'id' => $karyawan->id,
            'name' => $karyawan->nama_lengkap,
            'username' => $account->username ?? null,
            'email' => $account->email ?? null,
            'phone' => $karyawan->no_telpon,
            'avatar' => $karyawan->image,
            'branch_id' => $karyawan->id_cabang,
            'position_id' => $karyawan->id_jabatan,
            'employee' => $karyawan,
            'grade' => $karyawan->grade,
            'can_access_hrd_queue' => HrdPayroll::canAccessHrdQueue($karyawan),
        ];
    }

    private function logRequest(Request $request, $result, $name_req = null)
    {
        if (empty($request->all())) {
            return;
        }

        try {
            RequestLog::create([
                'name_req' => $name_req ?? $request->bearerToken(),
                'date_req' => Carbon::now(),
                'data_req' => json_encode($request->all()),
                'user_agent' => $request->header('User-Agent'),
                'result' => is_string($result) ? $result : json_encode($result),
                'path_info' => $request->path(),
                'ip' => $request->ip(),
                'platform' => $this->getPlatformFromUserAgent($request->header('User-Agent', '')),
            ]);
        } catch (\Throwable $e) {
            // logging tidak boleh gagalkan login
        }
    }

    private function getPlatformFromUserAgent($userAgent)
    {
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'Mac';
        }
        if (preg_match('/windows|win32/i', $userAgent)) {
            return 'Windows';
        }
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }
        if (preg_match('/iphone/i', $userAgent)) {
            return 'iOS';
        }

        return 'Other';
    }
}
