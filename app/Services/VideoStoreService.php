<?php

namespace App\Services;

class VideoStoreService
{
    public static function store(
        $video,
        $noSampel,
        $destination
    ) {
        if (!$video || !$video->isValid()) {
            throw new \Exception('File video tidak valid');
        }

        $extension = strtolower(
            $video->getClientOriginalExtension()
        );

        $allowedExtensions = [
            'mp4',
            'mov',
            'avi',
            'mkv',
            'webm',
        ];

        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception(
                'Format video tidak diizinkan'
            );
        }

        $noSampelSafe = preg_replace(
            '/[^A-Za-z0-9_\-]/',
            '_',
            $noSampel
        );

        $filename = 'video_'
            . time()
            . '_'
            . $noSampelSafe
            . '.'
            . $extension;

        return StoreFileService::storeUploadedFile(
            $destination,
            $video,
            $filename
        );
    }
}