<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token TTL (hari)
    |--------------------------------------------------------------------------
    | Masa aktif token login jika user tidak logout.
    */
    'token_ttl_days' => (int) env('AUTH_TOKEN_TTL_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Maksimum sesi aktif per user
    |--------------------------------------------------------------------------
    */
    'max_active_sessions' => (int) env('AUTH_MAX_SESSIONS', 3),

    /*
    |--------------------------------------------------------------------------
    | OTP Reset Password
    |--------------------------------------------------------------------------
    */
    'otp_length' => 6,
    'otp_ttl_minutes' => (int) env('AUTH_OTP_TTL_MINUTES', 10),
    'otp_max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),
    'otp_rate_limit' => (int) env('AUTH_OTP_RATE_LIMIT', 3),
    'otp_rate_limit_minutes' => (int) env('AUTH_OTP_RATE_LIMIT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Token cache TTL (detik) — Redis
    |--------------------------------------------------------------------------
    */
    'token_cache_ttl' => (int) env('AUTH_TOKEN_CACHE_TTL', 3600),
];
