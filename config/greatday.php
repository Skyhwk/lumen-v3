<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Greatday (attendance app) — koneksi & path
    |--------------------------------------------------------------------------
    */

    'apps_connection' => env('GREATDAY_APPS_CONNECTION', 'intilab_apps'),

    /** DB master karyawan / absensi (intilab_produksi di Internal) */
    'produksi_connection' => env('GREATDAY_PRODUKSI_CONNECTION', env('DB_CONNECTION', 'mysql')),

    'foto_karyawan_path' => env('V3_FOTO_PATH', '/var/www/html/v3/public/Foto_Karyawan'),

    'foto_absen_relative' => env('GREATDAY_FOTO_ABSEN_PATH', 'android-image/absensi'),

    /** RFID sync setelah absen mobile (MesinAbsenHandler /api/absen) */
    'absen_api_url' => rtrim(env('ABSEN_API', 'https://apps.intilab.com/v3/public/api/absen'), '/'),

    'portal_attendance_url' => rtrim(env('GREATDAY_PORTAL_URL', 'https://portal.intilab.com/attendance'), '/'),

    'login_token_ttl_days' => (int) env('GREATDAY_LOGIN_TOKEN_TTL_DAYS', 7),

    'web_public' => rtrim(env('WEB_PUBLIC', env('APP_PUBLIC_URL', 'https://apps.intilab.com/v3/public')), '/') . '/',

    /** Phase 2B: false = baca/tulis hr_* di produksi (setelah backfill + cutover) */
    'use_legacy_hr_tables' => filter_var(env('HR_USE_LEGACY_TABLES', true), FILTER_VALIDATE_BOOLEAN),

    /** Setelah backfill: sync approve/reject ke intilab_apps agar Super Apps (V3) tetap update */
    'dual_write_legacy_hr' => filter_var(env('HR_DUAL_WRITE_LEGACY', true), FILTER_VALIDATE_BOOLEAN),

    'legacy_apps_connection' => env('GREATDAY_APPS_CONNECTION', 'intilab_apps'),

    /** M7: true = auth greatday dari gd_user / gd_user_token di produksi */
    'use_produksi_gd_auth' => filter_var(env('GD_USE_PRODUKSI_AUTH', false), FILTER_VALIDATE_BOOLEAN),

    /** M7 migrasi: login tulis gd_user_token selain user_token (sebelum GD_USE_PRODUKSI_AUTH=true) */
    'dual_write_gd_auth' => filter_var(env('GD_DUAL_WRITE_AUTH', true), FILTER_VALIDATE_BOOLEAN),

    /** M8: notification, fcm, menu, permission dari gd_* produksi */
    'use_produksi_gd_app_data' => filter_var(env('GD_USE_PRODUKSI_APP_DATA', false), FILTER_VALIDATE_BOOLEAN),

    /** M6: stop sync hr_* → legacy apps (setara HR_DUAL_WRITE_LEGACY=false, eksplisit) */
    'freeze_legacy_hr_writes' => filter_var(env('HR_FREEZE_LEGACY_WRITES', false), FILTER_VALIDATE_BOOLEAN),

    /** Super Apps: true = list cuti/izin/lembur + antrian HRD IzinController baca hr_* */
    'portal_read_hr_tables' => filter_var(env('HR_PORTAL_READ_HR_TABLES', false), FILTER_VALIDATE_BOOLEAN),

];
