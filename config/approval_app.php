<?php

return [
    /**
     * URL publik Apps-Approval (tanpa trailing slash).
     * Contoh: https://apps.intilab.com/apps-approval
     * Hanya untuk email/tombol persetujuan Ibu Direktur & penyesuaian karyawan — bukan email ke kandidat (penawaran gaji tetap PORTALV4).
     * Kosong = link persetujuan direktur memakai PORTALV4.
     */
    'public_url' => rtrim((string) env('APPROVAL_APP_PUBLIC_URL', ''), '/'),

    'fcm' => [
        'enabled' => filter_var(env('APPROVAL_FCM_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        /** Path tetap: storage/app/firebase/intilab-approval.json */
        'credentials_path' => storage_path('app/firebase/intilab-approval.json'),
        /**
         * Email device yang login Apps-Approval (pisah koma). Kosong = kirim ke semua device terdaftar.
         * Contoh: hrd.intilab@gmail.com (EXPO_PUBLIC_GATE_EMAIL)
         */
        'device_emails' => array_values(array_unique(array_filter(array_map(
            static fn (string $part) => strtolower(trim($part)),
            preg_split('/[,;]+/', (string) env('APPROVAL_APP_DEVICE_EMAILS', ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )))),
    ],
];
