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

    /** RFID sync setelah absen mobile (MesinAbsenHandler /api/absen) */
    'absen_api_url' => rtrim(env('ABSEN_API', 'https://apps.intilab.com/v3/public/api/absen'), '/'),

    'portal_attendance_url' => rtrim(env('GREATDAY_PORTAL_URL', 'https://portal.intilab.com/attendance'), '/'),

    'login_token_ttl_days' => (int) env('GREATDAY_LOGIN_TOKEN_TTL_DAYS', 7),

    'web_public' => rtrim(env('WEB_PUBLIC', env('APP_PUBLIC_URL', 'https://apps.intilab.com/v3/public')), '/') . '/',

    /** Root folder aset publik Greatday: public/{asset_base}/{folder}/file */
    'asset_base' => trim(env('GREATDAY_ASSET_BASE', 'greatday'), '/'),

    'asset_folders' => [
        'cuti' => 'cuti',
        'izin' => 'izin',
        'koreksi_absen' => 'koreksi-absen',
        'absensi' => 'absensi',
        'lembur_reimburse' => 'lembur-reimburse',
        'laporan_kegiatan' => 'laporan-kegiatan',
    ],

    /** Lokasi lama (relatif public/) — dipakai resolve URL & sync file ke greatday/ */
    'asset_legacy_relative' => [
        'cuti' => ['leave-requests', 'android-image/leave-requests'],
        'izin' => ['permission-requests', 'android-image/lampiran_izin', 'android-image/permission-requests'],
        'koreksi_absen' => ['attendance-corrections', 'android-image/attendance-corrections', 'android-image/koreksi-absen'],
        'absensi' => ['android-image/absensi'],
        'lembur_reimburse' => ['overtime-reimbursements', 'android-image/overtime-reimbursements'],
        'laporan_kegiatan' => ['event-reports', 'android-image/event-reports'],
    ],

    /** Root public lama untuk artisan greatday:sync-form-attachment-files (bisa lebih dari satu). */
    'form_attachment_legacy_roots' => array_values(array_filter(array_map('trim', explode(',', env(
        'GREATDAY_LEGACY_PUBLIC_ROOTS',
        env('GREATDAY_LEGACY_PUBLIC_ROOT', '/var/www/html/v3/public')
    ))))),

    /** Intilab-Internal Super Apps lama — untuk greatday:import-assets-from-internal */
    'intilab_internal_public' => env('GREATDAY_INTILAB_INTERNAL_PUBLIC', ''),

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

    /** Super Apps: true = list cuti/izin/lembur + antrian HRD izin (permission saja) baca hr_* */
    'portal_read_hr_tables' => filter_var(env('HR_PORTAL_READ_HR_TABLES', false), FILTER_VALIDATE_BOOLEAN),

    /** Pengajuan cuti approved sebelum tanggal ini sudah termasuk opening_used_days (import Excel). */
    'leave_balance_cutover_at' => env('GREATDAY_LEAVE_BALANCE_CUTOVER_AT', '2026-04-01 00:00:00'),

    /** Validasi cuti tahunan (Fase B) */
    'leave_annual_min_days_before' => (int) env('GREATDAY_LEAVE_MIN_DAYS_BEFORE', 14),
    'leave_annual_rolling_window_days' => (int) env('GREATDAY_LEAVE_ROLLING_WINDOW_DAYS', 30),
    'leave_annual_max_days_per_window' => (int) env('GREATDAY_LEAVE_MAX_DAYS_PER_WINDOW', 3),

    /** Fase C — pengganti hari libur (PHL) & gabung tahunan */
    'leave_phl_max_weekdays_per_window' => (int) env('GREATDAY_LEAVE_PHL_MAX_PER_WINDOW', 1),
    'leave_combined_max_weekdays_per_window' => (int) env('GREATDAY_LEAVE_COMBINED_MAX_PER_WINDOW', 4),

    /** Fase D — potong saldo per hari alpa (ledger) */
    'leave_alpa_days_delta' => (int) env('GREATDAY_LEAVE_ALPA_DAYS_DELTA', 1),

    /** Grade tanpa absensi wajib — tidak di-sync alpa (Manager, SM, Director) */
    'leave_alpa_exempt_grades' => ['MANAGER', 'SENIOR MANAGER', 'DIRECTOR', 'DIREKTUR'],

];
