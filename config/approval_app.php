<?php

return [
    /**
     * URL publik Apps-Approval (tanpa trailing slash).
     * Contoh: https://apps.intilab.com/apps-approval
     * Kosong = link email tetap memakai PORTALV4 / FRONTEND_URL.
     */
    'public_url' => rtrim((string) env('APPROVAL_APP_PUBLIC_URL', ''), '/'),

    'fcm' => [
        'enabled' => filter_var(env('APPROVAL_FCM_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        /** Path tetap: storage/app/firebase/intilab-approval.json */
        'credentials_path' => storage_path('app/firebase/intilab-approval.json'),
    ],
];
