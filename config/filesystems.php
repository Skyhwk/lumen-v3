<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local_public'),

    'disks' => [

        'local_public' => [
            'driver' => 'local',
            'root' => base_path('public'),
            'url' => rtrim(env('APP_URL'), '/'),
            'visibility' => 'public',
        ],

        // Untuk AWS nanti
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
        ],

    ],

];