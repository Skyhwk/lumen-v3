<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\MasterKaryawan;
use App\Models\UserToken;
use App\Cache\TokenCacheService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /** @var TokenCacheService */
    private $tokenCache;

    public function __construct(Request $request, TokenCacheService $tokenCache)
    {
        parent::__construct($request);
        $this->tokenCache = $tokenCache;
    }

    public function index(Request $request)
    {
        $data = User::with(['karyawan'])
            ->select('users.id', 'users.username', 'users.email')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'nama_lengkap' => $user->karyawan ? $user->karyawan->nama_lengkap : null,
                ];
            });

        return response()->json([
            'data' => $data,
        ]);
    }

    public function impersonate(Request $request)
    {
        $token = $this->resolveToken($request);
        $resolved = $this->resolveTokenOrError($token);

        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }

        $userToken = $resolved;

        $requestedImpersonatorId = $this->normalizeUserId($request->input('impersonator_user_id'));
        $repair = $this->repairBrokenImpersonation($userToken, $token, $requestedImpersonatorId);

        if ($repair === false) {
            return response()->json([
                'message' => 'Sesi impersonate tidak valid. Silakan logout dan login ulang.',
            ], 400);
        }

        $userToken->refresh();

        $actorUserId = $this->resolveActorUserId($userToken, $requestedImpersonatorId);
        if (!$this->canImpersonate($actorUserId)) {
            return response()->json(['message' => 'Anda tidak memiliki akses impersonate'], 403);
        }

        $targetUser = User::where('id', $request->user_id)->where('is_active', true)->first();
        if (!$targetUser) {
            return response()->json(['message' => 'User tujuan tidak ditemukan'], 404);
        }

        if ((int) $targetUser->id === (int) $actorUserId) {
            return response()->json(['message' => 'Tidak perlu impersonate akun sendiri'], 400);
        }

        $impersonatorId = $this->normalizeUserId($userToken->impersonator_user_id) ?: $actorUserId;

        UserToken::where('id', $userToken->id)->update([
            'impersonator_user_id' => $impersonatorId,
            'user_id' => (int) $targetUser->id,
            'is_impersonate' => 1,
        ]);

        $this->tokenCache->invalidateTokenCache($token);

        return response()->json([
            'message' => 'Berhasil impersonate user',
            'impersonator_user_id' => (int) $impersonatorId,
        ]);
    }

    public function stopImpersonate(Request $request)
    {
        $token = $this->resolveToken($request);
        $resolved = $this->resolveTokenOrError($token);

        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }

        $userToken = $resolved;

        if (!$this->tokenIsImpersonating($userToken)) {
            return response()->json(['message' => 'Anda tidak sedang impersonate'], 400);
        }

        $restoreUserId = $this->normalizeUserId($userToken->impersonator_user_id);

        if (!$restoreUserId) {
            $restoreUserId = $this->normalizeUserId($request->input('impersonator_user_id'));
        }

        if (!$restoreUserId || !$this->canImpersonate($restoreUserId)) {
            return response()->json([
                'message' => 'Tidak dapat kembali ke akun asli. Silakan logout dan login ulang.',
            ], 400);
        }

        if (!$this->userHasKaryawan($restoreUserId)) {
            return response()->json([
                'message' => 'Akun asli tidak valid. Silakan logout dan login ulang.',
            ], 400);
        }

        UserToken::where('id', $userToken->id)->update([
            'user_id' => $restoreUserId,
            'impersonator_user_id' => null,
            'is_impersonate' => 0,
        ]);

        $this->tokenCache->invalidateTokenCache($token);

        return response()->json([
            'message' => 'Berhasil kembali ke akun asli',
        ]);
    }

    private function resolveToken(Request $request)
    {
        return $request->header('token') ?: $request->token;
    }

    private function resolveTokenOrError($token)
    {
        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 430);
        }

        $userToken = UserToken::where('token', $token)->first();

        if (!$userToken) {
            return response()->json(['message' => 'Token tidak ditemukan'], 430);
        }

        if (!$userToken->isActive()) {
            return response()->json([
                'message' => 'Sesi telah berakhir, silakan login ulang',
            ], 430);
        }

        return $userToken;
    }

    private function normalizeUserId($value)
    {
        $userId = (int) $value;

        return $userId > 0 ? $userId : null;
    }

    private function tokenIsImpersonating(UserToken $userToken)
    {
        return (int) $userToken->is_impersonate === 1;
    }

    private function isBrokenImpersonation(UserToken $userToken)
    {
        return $this->tokenIsImpersonating($userToken) && empty($userToken->impersonator_user_id);
    }

    /**
     * @return bool|null true = repaired, false = failed, null = not broken
     */
    private function repairBrokenImpersonation(UserToken $userToken, $token, $requestedImpersonatorId = null)
    {
        if (!$this->isBrokenImpersonation($userToken)) {
            return null;
        }

        if ($requestedImpersonatorId && $this->canImpersonate($requestedImpersonatorId)) {
            UserToken::where('id', $userToken->id)->update([
                'impersonator_user_id' => $requestedImpersonatorId,
            ]);
            $this->tokenCache->invalidateTokenCache($token);

            return true;
        }

        if ($this->canImpersonate((int) $userToken->user_id)) {
            UserToken::where('id', $userToken->id)->update([
                'is_impersonate' => 0,
                'impersonator_user_id' => null,
            ]);
            $this->tokenCache->invalidateTokenCache($token);

            return true;
        }

        return false;
    }

    private function resolveActorUserId(UserToken $userToken, $requestedImpersonatorId = null)
    {
        $storedImpersonatorId = $this->normalizeUserId($userToken->impersonator_user_id);
        if ($storedImpersonatorId) {
            return $storedImpersonatorId;
        }

        if ($requestedImpersonatorId && $this->canImpersonate($requestedImpersonatorId)) {
            return $requestedImpersonatorId;
        }

        if (!$this->tokenIsImpersonating($userToken)) {
            return (int) $userToken->user_id;
        }

        return (int) $userToken->user_id;
    }

    private function canImpersonate($userId)
    {
        $karyawan = MasterKaryawan::where('user_id', $userId)->first();

        if (!$karyawan) {
            return false;
        }

        return in_array((int) $karyawan->id, [1, 127, 152], true);
    }

    private function userHasKaryawan($userId)
    {
        return MasterKaryawan::where('user_id', $userId)->exists();
    }
}
