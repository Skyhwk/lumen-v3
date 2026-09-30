<?php

return [
    'allowed_origins' => array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,http://localhost:4173,http://127.0.0.1:4173'
    )))),
    'allowed_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
    'allowed_headers' => 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-Slice, X-Greatday-Token, x-greatday-token, token',
    'max_age' => 86400,
];
