<?php

namespace App\Services;

use App\Cache\TokenCacheService;
use App\Models\UserToken;
use Carbon\Carbon;

class AuthTokenService
{
    /** @var TokenCacheService */
    private $tokenCache;

    public function __construct(TokenCacheService $tokenCache)
    {
        $this->tokenCache = $tokenCache;
    }

    public function getTokenTtlDays()
    {
        return $this->getRememberTokenTtlDays();
    }

    public function getRememberTokenTtlDays()
    {
        return (int) config('auth.token_ttl_remember_days', 365);
    }

    public function getSessionTokenTtlDays()
    {
        return (int) config('auth.token_ttl_session_days', 90);
    }

    public function resolveLoginTokenTtlDays($rememberMe)
    {
        return $rememberMe
            ? $this->getRememberTokenTtlDays()
            : $this->getSessionTokenTtlDays();
    }

    /**
     * Ambil TTL asli token dari selisih create_date ↔ expired.
     */
    public function resolveTokenTtlDays(UserToken $userToken)
    {
        if (!empty($userToken->create_date) && !empty($userToken->expired)) {
            $days = Carbon::parse($userToken->create_date)
                ->diffInDays(Carbon::parse($userToken->expired));

            if ($days > 0) {
                return (int) $days;
            }
        }

        return $this->getSessionTokenTtlDays();
    }

    public function getMaxActiveSessions()
    {
        return (int) config('auth.max_active_sessions', 3);
    }

    public function calculateExpiryDate($from = null, $ttlDays = null)
    {
        $base = $from ? Carbon::parse($from) : Carbon::now();
        $days = $ttlDays ?? $this->getSessionTokenTtlDays();

        return $base->copy()->addDays($days)->toDateTimeString();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|UserToken[]
     */
    public function getActiveSessions($userId)
    {
        return UserToken::where('user_id', $userId)
            ->loginSessions()
            ->active()
            ->orderBy('create_date', 'asc')
            ->get();
    }

    public function countActiveSessions($userId)
    {
        return UserToken::where('user_id', $userId)
            ->loginSessions()
            ->active()
            ->count();
    }

    /**
     * Expire sesi paling lama dan invalidate cache-nya.
     *
     * @return UserToken|null
     */
    public function expireOldestSession($userId)
    {
        $oldest = UserToken::where('user_id', $userId)
            ->loginSessions()
            ->active()
            ->orderBy('create_date', 'asc')
            ->first();

        if (!$oldest) {
            return null;
        }

        $this->expireToken($oldest);

        return $oldest;
    }

    public function expireToken(UserToken $userToken)
    {
        $userToken->is_expired = true;
        $userToken->is_logged_in = false;
        $userToken->save();

        $this->tokenCache->invalidateTokenCache($userToken->token);
    }

    /**
     * Expire semua token user (force re-login).
     */
    public function expireAllUserTokens($userId)
    {
        $tokens = UserToken::where('user_id', $userId)
            ->where('is_expired', false)
            ->get();

        foreach ($tokens as $token) {
            $this->expireToken($token);
        }

        $this->tokenCache->invalidateAllTokensForUser($userId);
    }

    /**
     * Buat token login baru.
     */
    public function createLoginToken($userId, array $sessionMeta = [])
    {
        $now = Carbon::now()->toDateTimeString();

        $userToken = new UserToken();
        $userToken->user_id = $userId;
        $userToken->token = bin2hex(random_bytes(40)) . strtotime($now);
        $userToken->create_date = $now;
        $ttlDays = (int) ($sessionMeta['token_ttl_days'] ?? $this->getSessionTokenTtlDays());
        $userToken->expired = $this->calculateExpiryDate($now, $ttlDays);
        $userToken->is_logged_in = true;
        $userToken->is_expired = false;
        $userToken->type = 'private';
        $userToken->platform = $sessionMeta['platform'] ?? null;
        $userToken->user_agent = $sessionMeta['user_agent'] ?? null;
        $userToken->ip_address = $sessionMeta['ip_address'] ?? null;
        $userToken->last_active_at = $now;
        $userToken->save();

        return $userToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSessionMetaFromRequest($request)
    {
        $userAgent = $request->header('User-Agent');

        return [
            'platform' => DeviceParser::parse($userAgent),
            'user_agent' => $userAgent,
            'ip_address' => $request->ip(),
        ];
    }

    /**
     * Buat sesi login baru dengan penanganan batas perangkat.
     *
     * @return array<string, mixed>
     */
    public function attemptCreateSession($userId, array $sessionMeta, $confirmDisplace = false)
    {
        $maxSessions = $this->getMaxActiveSessions();
        $activeCount = $this->countActiveSessions($userId);
        $displaced = null;

        if ($activeCount >= $maxSessions) {
            $oldest = UserToken::where('user_id', $userId)
                ->loginSessions()
                ->active()
                ->orderBy('create_date', 'asc')
                ->first();

            if (!$confirmDisplace) {
                return [
                    'status' => 'limit_reached',
                    'current_device' => $sessionMeta['platform'] ?? 'Perangkat Lain',
                    'oldest' => $oldest,
                    'active_sessions' => $this->formatActiveSessions($userId, $oldest ? $oldest->id : null),
                ];
            }

            $displaced = $this->expireOldestSession($userId);
        }

        $token = $this->createLoginToken($userId, $sessionMeta);

        return [
            'status' => 'success',
            'token' => $token,
            'displaced' => $displaced,
        ];
    }

    /**
     * Perpanjang masa aktif sesi (sliding expiry) saat user masih aktif.
     */
    public function touchSession(UserToken $userToken, $minIntervalMinutes = 5)
    {
        if (!$userToken || empty($userToken->id)) {
            return;
        }

        $now = Carbon::now();
        $lastActive = $userToken->last_active_at
            ? Carbon::parse($userToken->last_active_at)
            : null;

        if ($lastActive && $lastActive->diffInMinutes($now) < $minIntervalMinutes) {
            return;
        }

        $nowStr = $now->toDateTimeString();
        $ttlDays = $this->resolveTokenTtlDays($userToken);
        $newExpiry = $this->calculateExpiryDate($nowStr, $ttlDays);

        UserToken::where('id', $userToken->id)->update([
            'last_active_at' => $nowStr,
            'expired' => $newExpiry,
        ]);

        $userToken->last_active_at = $nowStr;
        $userToken->expired = $newExpiry;

        $this->tokenCache->invalidateTokenCache($userToken->token);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function formatActiveSessions($userId, $displacedTokenId = null)
    {
        return $this->getActiveSessions($userId)->map(function (UserToken $token) use ($displacedTokenId) {
            $summary = DeviceParser::formatSessionSummary($token);
            $summary['will_be_removed'] = $displacedTokenId !== null && (int) $token->id === (int) $displacedTokenId;

            return $summary;
        })->values()->all();
    }
}
