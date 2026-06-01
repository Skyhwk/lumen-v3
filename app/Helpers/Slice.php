<?php

namespace App\Helpers;

use App\Services\Crypto;

class Slice
{
    public static function makeSlice(string $controller, string $function, bool $includeTimestamp = true): string
    {
        $data = [
            'controller' => $controller,
            'function' => $function,
        ];

        if ($includeTimestamp) {
            $data['ts'] = time();
        }

        return app(Crypto::class)->encryptSlice(json_encode($data));
    }

    public static function makeDecrypt(string $data, bool $validateTtl = true)
    {
        return app(Crypto::class)->decryptSlice($data, $validateTtl);
    }
}
