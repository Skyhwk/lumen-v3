<?php

namespace App\Helpers;

class FrontendPublicUrl
{
    /**
     * Build URL ke halaman public React (di luar shell /frontend login).
     * Contoh: build('private/assessment/abc')
     *   dev  → http://localhost:3000/frontend/private/assessment/abc
     *   prod → https://example.com/frontend/private/assessment/abc
     */
    public static function build(string $path, ?string $originOverride = null): string
    {
        $base = rtrim(self::resolveBaseOrigin($originOverride), '/');
        $basePath = trim((string) env('FRONTEND_BASE_PATH', 'frontend'), '/');

        if ($basePath !== '') {
            $base .= '/' . $basePath;
        }

        return $base . '/' . ltrim($path, '/');
    }

    public static function buildAssessmentLink(string $token, ?string $originOverride = null): string
    {
        return self::build('private/assessment/' . rawurlencode($token), $originOverride);
    }

    public static function resolveBaseOrigin(?string $override = null): string
    {
        $override = trim((string) $override);
        if ($override !== '') {
            return rtrim($override, '/');
        }

        $fromEnv = trim((string) env('FRONTEND_URL', ''));
        if ($fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        $fromRequest = self::originFromRequest();
        if ($fromRequest !== '') {
            return $fromRequest;
        }

        $reactUrl = trim((string) env('REACT_APP_URL', ''));
        if ($reactUrl !== '') {
            return rtrim($reactUrl, '/');
        }

        return 'http://localhost:3000';
    }

    private static function originFromRequest(): string
    {
        try {
            $request = request();
            if (!$request) {
                return '';
            }

            foreach (['Origin', 'Referer'] as $header) {
                $value = trim((string) $request->header($header, ''));
                if ($value === '') {
                    continue;
                }

                $parts = parse_url($value);
                if (empty($parts['scheme']) || empty($parts['host'])) {
                    continue;
                }

                $port = isset($parts['port']) ? ':' . $parts['port'] : '';

                return $parts['scheme'] . '://' . $parts['host'] . $port;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }
}
