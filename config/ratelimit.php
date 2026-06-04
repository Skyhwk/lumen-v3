<?php

return [
    'enabled' => env('RATE_LIMIT_ENABLED', true),

    // IP exact (10.88.209.222) atau prefix wildcard (10.88.*, 192.168.*)
    'whitelist_ips' => array_values(array_filter(array_map('trim', explode(',', env('RATE_LIMIT_WHITELIST_IPS', ''))))),

    'rules' => [
        'default' => [
            'max_attempts' => (int) env('RATE_LIMIT_DEFAULT_MAX', 120),
            'decay_minutes' => (int) env('RATE_LIMIT_DEFAULT_DECAY', 1),
        ],

        'groups' => [
            [
                'name' => 'auth',
                'paths' => [
                    'api/gettoken',
                    'api/cektoken',
                    'director/login',
                ],
                'max_attempts' => (int) env('RATE_LIMIT_AUTH_MAX', 10),
                'decay_minutes' => (int) env('RATE_LIMIT_AUTH_DECAY', 1),
            ],
            [
                'name' => 'iot',
                'paths' => [
                    'api/absen',
                    'api/device_intilab',
                    'api/multi-device',
                    'api/iot-intilab',
                    'api/sensorData',
                    'api/soundMeterData',
                ],
                'max_attempts' => (int) env('RATE_LIMIT_IOT_MAX', 300),
                'decay_minutes' => (int) env('RATE_LIMIT_IOT_DECAY', 1),
            ],
        ],
    ],

    'authenticated' => [
        // /api/route & /api/mobile — limit per user (bukan per IP, aman untuk NAT kantor)
        'max_attempts' => (int) env('RATE_LIMIT_USER_MAX', 600),
        'decay_minutes' => (int) env('RATE_LIMIT_USER_DECAY', 1),
    ],
];
