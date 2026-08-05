<?php

namespace App\Services;

class ImageConvertService
{
    public static function fromBase64($foto, $type, $user, $destination)
    {
        $img = str_replace(
            'data:image/jpeg;base64,',
            '',
            $foto
        );

        $file = base64_decode($img, true);

        if ($file === false) {
            throw new \Exception('Format base64 image tidak valid');
        }

        $safeName = date('YmdHis')
            . '_'
            . $user
            . '_'
            . $type
            . '.jpeg';

        return StoreFileService::store(
            $destination,
            $safeName,
            $file
        );
    }
}