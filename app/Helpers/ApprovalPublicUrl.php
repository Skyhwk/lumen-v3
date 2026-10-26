<?php

namespace App\Helpers;

class ApprovalPublicUrl
{
    public static function base(): string
    {
        return rtrim((string) config('approval_app.public_url', ''), '/');
    }

    public static function build(string $path): string
    {
        $base = self::base();
        if ($base === '') {
            return '';
        }

        return $base . '/' . ltrim($path, '/');
    }
}
