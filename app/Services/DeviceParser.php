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

        $superAppsLabel = self::parseSuperAppsLabel($userAgent);
        if ($superAppsLabel !== null) {
            return $superAppsLabel;
        }

        if (preg_match('/Thunder Client/i', $userAgent)) {
            return 'Thunder Client';
        }

        $os = self::detectOsLabel($userAgent);
        $browser = self::detectBrowserLabel($userAgent);

        if ($os && $browser) {
            return $os . ' ' . $browser;
        }

        if ($os) {
            return $os;
        }

        if ($browser) {
            return $browser;
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

    private static function parseSuperAppsLabel(string $userAgent): ?string
    {
        if (preg_match('/intilab-apps\/([\d]+(?:\.[\d]+)*)/i', $userAgent, $matches)) {
            return 'Aplikasi Super Apps ' . $matches[1];
        }

        if (preg_match('/Electron/i', $userAgent)) {
            return 'Aplikasi Super Apps';
        }

        return null;
    }

    private static function detectOsLabel(string $userAgent): ?string
    {
        if (preg_match('/ipad/i', $userAgent)) {
            return 'iPad';
        }

        if (preg_match('/iphone/i', $userAgent)) {
            return 'iPhone';
        }

        if (preg_match('/android/i', $userAgent)) {
            return preg_match('/mobile/i', $userAgent) ? 'Android' : 'Android Tablet';
        }

        if (preg_match('/windows phone/i', $userAgent)) {
            return 'Windows Phone';
        }

        if (preg_match('/cros/i', $userAgent)) {
            return 'ChromeOS';
        }

        if (preg_match('/windows nt|win32/i', $userAgent)) {
            return 'Windows';
        }

        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'MacOS';
        }

        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }

        return null;
    }

    private static function detectBrowserLabel(string $userAgent): ?string
    {
        if (preg_match('/Edg\//i', $userAgent)) {
            return 'Edge';
        }

        if (preg_match('/OPR\/|Opera/i', $userAgent)) {
            return 'Opera';
        }

        if (preg_match('/Firefox\//i', $userAgent)) {
            return 'Firefox';
        }

        if (preg_match('/Chrome\//i', $userAgent)) {
            return 'Chrome';
        }

        if (preg_match('/Safari\//i', $userAgent)) {
            return 'Safari';
        }

        return null;
    }
}
