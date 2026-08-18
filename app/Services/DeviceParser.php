<?php

namespace App\Services;

class DeviceParser
{
    /**
     * Parse User-Agent menjadi label yang mudah dibaca user.
     */
    public static function parse(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Perangkat Lain';
        }

        if (preg_match('/ipad/i', $userAgent)) {
            return 'iPad (Tablet)';
        }

        if (preg_match('/iphone/i', $userAgent)) {
            return 'iPhone';
        }

        if (preg_match('/android/i', $userAgent)) {
            if (preg_match('/mobile/i', $userAgent)) {
                return 'Android (HP)';
            }

            return 'Android (Tablet)';
        }

        if (preg_match('/windows phone/i', $userAgent)) {
            return 'Windows Phone';
        }

        if (preg_match('/windows|win32/i', $userAgent)) {
            return 'Windows (PC)';
        }

        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'Mac (PC)';
        }

        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux (PC)';
        }

        if (preg_match('/cros/i', $userAgent)) {
            return 'Chromebook';
        }

        if (preg_match('/Thunder Client/i', $userAgent)) {
            return 'Thunder Client';
        }

        return 'Perangkat Lain';
    }

    /**
     * Ringkasan sesi untuk response API login.
     */
    public static function formatSessionSummary($token): array
    {
        return [
            'platform' => $token->platform ?: self::parse($token->user_agent),
            'login_at' => $token->create_date,
            'ip_address' => $token->ip_address,
            'last_active_at' => $token->last_active_at,
        ];
    }
}
