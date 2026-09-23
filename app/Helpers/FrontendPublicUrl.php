<?php

namespace App\Helpers;

class FrontendPublicUrl
{
    /**
     * Build URL ke halaman public React (di luar shell /frontend login).
     * Contoh: build('private/assessment/abc')
     *   → http://localhost:3000/frontend/private/assessment/abc
     */
    public static function build(string $path): string
    {
        $base = rtrim((string) env('FRONTEND_URL', env('REACT_APP_URL', 'http://localhost:3000')), '/');
        $basePath = trim((string) env('FRONTEND_BASE_PATH', 'frontend'), '/');

        if ($basePath !== '') {
            $base .= '/' . $basePath;
        }

        return $base . '/' . ltrim($path, '/');
    }
}
