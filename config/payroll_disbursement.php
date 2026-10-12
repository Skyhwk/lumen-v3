<?php

return [

    /*
    | Endpoint worker Python (transfer payroll / notifikasi massal).
    | Contoh: POST {base}/payroll/disburse
    */
    'python_base_url' => rtrim(env('PAYROLL_DISBURSEMENT_SERVICE', env('PDF_COMBINER_SERVICE', 'http://127.0.0.1:2999')), '/'),

    'python_disburse_path' => env('PAYROLL_DISBURSEMENT_PATH', '/payroll/disburse'),

    /** Shared secret: Python wajib kirim di header X-Payroll-Callback-Token atau field token */
    'callback_token' => env('PAYROLL_DISBURSEMENT_CALLBACK_TOKEN', ''),

    /**
     * URL callback penuh (override). Kosong = {WEB_PUBLIC}/api/payroll-transfer/complete
     * WEB_PUBLIC contoh: https://apps.intilab.com/v3/public
     */
    'callback_url' => env('PAYROLL_DISBURSEMENT_CALLBACK_URL', ''),

    'http_timeout_seconds' => (int) env('PAYROLL_DISBURSEMENT_HTTP_TIMEOUT', 15),

    /** Jika false, transfer payroll tidak memanggil Python (hanya simpan tgl_transfer). */
    'enabled' => filter_var(env('PAYROLL_DISBURSEMENT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

];
