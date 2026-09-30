<?php

return [
    'timezone' => 'Asia/Jakarta',
    'discovery' => [
        'timezone' => env('QUOTATION_DISCOVERY_TIMEZONE', 'Asia/Jakarta'),
        'quotation_months' => (int) env('QUOTATION_DISCOVERY_QUOTATION_MONTHS', 3),
        'order_months' => (int) env('QUOTATION_DISCOVERY_ORDER_MONTHS', 6),
    ],
    'generate' => [
        'default_batch_size' => (int) env('QUOTATION_GENERATE_BATCH_SIZE', 100),
        // quotation:dispatch — kelompok log per chunk; jeda antar setiap QT (detik).
        'dispatch_chunk_size' => (int) env('QUOTATION_DISPATCH_CHUNK_SIZE', 100),
        'dispatch_item_pause_seconds' => (int) env('QUOTATION_DISPATCH_ITEM_PAUSE_SECONDS', 5),
        'dispatch_chunk_pause_seconds' => (int) env('QUOTATION_DISPATCH_CHUNK_PAUSE_SECONDS', 0),
        'created_by' => env('QUOTATION_GENERATE_CREATED_BY', 'Quotation Auto Generate'),
        'generated_by' => env('QUOTATION_GENERATE_GENERATED_BY', 'Quotation Auto Generate'),
        'emailed_by' => env('QUOTATION_GENERATE_EMAILED_BY', 'Quotation Auto Generate'),
        'approved_by' => env('QUOTATION_GENERATE_APPROVED_BY', 'Lani Febriana Safitri'),
        // Penanda di request_quotation baru hasil auto-generate (discovery mengabaikan QT dengan kode_promo terisi).
        'kode_promo_marker' => env('QUOTATION_GENERATE_KODE_PROMO', 'AUTO'),
        'default_email_cc' => [],
        'default_email_bcc' => ['sales@intilab.com'],
        // Email — nyalakan dengan QUOTATION_GENERATE_SEND_EMAIL=true
        'send_email' => filter_var(env('QUOTATION_GENERATE_SEND_EMAIL', false), FILTER_VALIDATE_BOOLEAN),
        // Mode uji: To dipaksa ke email_test_to, CC/BCC kosong. Produksi: false → To dari email_pic_order.
        'email_test_mode' => filter_var(env('QUOTATION_GENERATE_EMAIL_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),
        'email_test_to' => env('QUOTATION_GENERATE_EMAIL_TEST_TO', ''),
        // Nama & jabatan di blok signature (hardcode — selaras template email penawaran).
        'signature_display_name' => env('QUOTATION_SIGNATURE_DISPLAY_NAME', 'Aisyah Wulandari'),
        'signature_display_jabatan' => env('QUOTATION_SIGNATURE_DISPLAY_JABATAN', 'Sales Admin Staff'),
        // QR signature = asset bawaan Qt Approved ComposeMail (base64 JPEG).
        'signature_qr_data_uri' => include __DIR__ . '/quotation_email_signature_qr.php',
        'signature_company_phone' => env('QUOTATION_SIGNATURE_COMPANY_PHONE', '021 50898988'),
        'signature_company_email' => env('QUOTATION_SIGNATURE_COMPANY_EMAIL', 'admsales01@intilab.com'),
        'signature_company_website' => env('QUOTATION_SIGNATURE_COMPANY_WEBSITE', 'www.intilab.com'),
        // Optional override logo footer: [{ "path": "img/email-signature/logo-isl.png", "width": 220, "height": 63 }, ...]
        'signature_footer_logos' => [],
    ],
];