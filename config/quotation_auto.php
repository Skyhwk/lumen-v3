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
        'create_item_pause_seconds' => (int) env('QUOTATION_CREATE_ITEM_PAUSE_SECONDS', 1),
        // quotationdispatch — kelompok log per chunk; jeda antar setiap QT (detik).
        'dispatch_chunk_size' => (int) env('QUOTATION_DISPATCH_CHUNK_SIZE', 100),
        'dispatch_item_pause_seconds' => (int) env('QUOTATION_DISPATCH_ITEM_PAUSE_SECONDS', 5),
        'dispatch_chunk_pause_seconds' => (int) env('QUOTATION_DISPATCH_CHUNK_PAUSE_SECONDS', 0),
        'email_item_pause_seconds' => (int) env('QUOTATION_EMAIL_ITEM_PAUSE_SECONDS', 1),
        'created_by' => env('QUOTATION_GENERATE_CREATED_BY', 'Quotation Auto Generate'),
        'generated_by' => env('QUOTATION_GENERATE_GENERATED_BY', 'Quotation Auto Generate'),
        'emailed_by' => env('QUOTATION_GENERATE_EMAILED_BY', 'Quotation Auto Generate'),
        'approved_by' => env('QUOTATION_GENERATE_APPROVED_BY', 'Lani Febriana Safitri'),
        // Penanda di request_quotation baru hasil auto-generate (discovery mengabaikan QT dengan kode_promo terisi).
        'kode_promo_marker' => env('QUOTATION_GENERATE_KODE_PROMO', 'AUTO'),
        'default_email_bcc' => ['sales@intilab.com'],
        // Email — nyalakan dengan QUOTATION_GENERATE_SEND_EMAIL=true
        'send_email' => filter_var(env('QUOTATION_GENERATE_SEND_EMAIL', false), FILTER_VALIDATE_BOOLEAN),
        // Mode uji: To dipaksa ke email_test_to. Produksi: To dari email_pic_order.
        'email_test_mode' => filter_var(env('QUOTATION_GENERATE_EMAIL_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),
        'email_test_to' => env('QUOTATION_GENERATE_EMAIL_TEST_TO', ''),
        // Mode tes: anggap sales PJ-nya orang ini (BCC dia + atasan SUPERVISOR-nya).
        'email_test_sales' => env('QUOTATION_GENERATE_EMAIL_TEST_SALES', 'harold@intilab.com'),
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
        'resolve_sales_from_master' => false,
        'fallback_sales_id' => 1068,
        'source_skip_kode_promo' => [],
        'source_skip_kode_promo_prefix' => [],
        'recalculate_sampling_prices' => false,
        'clear_source_discounts' => false,
        'require_promo_id' => false,
        'require_source_data_pendukung' => false,
        'tanggal_penawaran' => null,
    ],
    'campaigns' => [
        'jan_to_june' => [
            'kode_promo_marker' => 'Auto_Jan_to_June',
            'created_by' => 'Quotation Auto Generate Jan to June',
            'generated_by' => 'Quotation Auto Generate Jan to June',
            'emailed_by' => 'Quotation Auto Generate Jan to June',
            'resolve_sales_from_master' => true,
            'fallback_sales_id' => 1068,
            'source_skip_kode_promo' => ['AUTO', 'Auto_Jan_to_June'],
            'recalculate_sampling_prices' => false,
        ],
        'auto_qt' => [
            'kode_promo_marker' => 'AUTO',
            'created_by' => 'Quotation Auto Generate',
            'generated_by' => 'Quotation Auto Generate',
            'emailed_by' => 'Quotation Auto Generate',
            'resolve_sales_from_master' => true,
            'fallback_sales_id' => 1068,
            'source_skip_kode_promo' => ['AUTO', 'Auto_Jan_to_June'],
            'source_skip_kode_promo_prefix' => ['Auto_'],
            'recalculate_sampling_prices' => true,
            'clear_source_discounts' => true,
            'require_promo_id' => true,
            'require_source_data_pendukung' => true,
        ],
    ],
    'python' => [
        'invoke_url' => rtrim((string) env('PDF_COMBINER_SERVICE', 'http://10.88.22.231:2999'), '/') . '/lumen/invoke',
        'invoke_secret' => env('QUOTATION_GENERATE_INVOKE_SECRET', env('INVOKE_SECRET', '')),
        'invoke_timeout_seconds' => (int) env('QUOTATION_GENERATE_PYTHON_TIMEOUT', 1800),
        'lumen_timeout_seconds' => (int) env('QUOTATION_GENERATE_LUMEN_TIMEOUT', 1700),
        'controller' => 'QuotationGenerateJanToJuneController',
        'run_function' => 'run',
        'test_function' => 'runTest',
    ],
    'python_auto_qt' => [
        'controller' => 'QuotationGenerateAutoQtController',
        'run_function' => 'run',
        'test_function' => 'runTest',
    ],
    'link_tracking' => [
        // Base portal customer (link tracking di email), mis. http://localhost:8000
        'portal_base' => rtrim((string) env('PORTALV4', ''), '/'),
        // Base halaman auth penawaran setelah redirect (token ditempel), mis. http://localhost:8000/public/auth/
        'portal_auth_base' => rtrim((string) env('PORTALV3_LINK', env('PORTAL_API', '')), '/'),
        'portal_redirect_path' => env('QUOTATION_AUTO_PORTAL_REDIRECT_PATH', '/public/auth/redirect'),
    ],
];