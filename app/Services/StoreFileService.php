<?php

namespace App\Services;

class StoreFileService
{
    private static function disk()
    {
        return app('filesystem')->disk(
            config('filesystems.default')
        );
    }

    private static function key($destination, $filename)
    {
        return trim($destination, '/')
            . '/'
            . ltrim($filename, '/');
    }

    public static function store(
        $destination,
        $filename,
        $contents,
        $visibility = 'public'
    ) {
        $isStored = self::disk()->put(
            self::key($destination, $filename),
            $contents,
            ['visibility' => $visibility]
        );

        if (!$isStored) {
            throw new \Exception('Gagal menyimpan file: ' . $filename);
        }

        return $filename;
    }

    public static function storeUploadedFile(
        $destination,
        $uploadedFile,
        $filename,
        $visibility = 'public'
    ) {
        $storedPath = self::disk()->putFileAs(
            trim($destination, '/'),
            $uploadedFile,
            $filename,
            [
                'visibility' => $visibility,
            ]
        );

        if ($storedPath === false) {
            throw new \Exception(
                'Gagal menyimpan file: ' . $filename
            );
        }

        return $filename;
    }

    public static function url($destination, $filename)
    {
        if (!$filename) {
            return null;
        }

        return self::disk()->url(
            self::key($destination, $filename)
        );
    }

    public static function delete($destination, $filename)
    {
        if (!$filename) {
            return false;
        }

        return self::disk()->delete(
            self::key($destination, $filename)
        );
    }
}