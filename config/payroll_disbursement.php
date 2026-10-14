<?php

return [

    /*
    | Satu host dengan combine LHP: PDF_COMBINER_SERVICE + /lumen/invoke
    */
    'python_invoke_url' => rtrim((string) env('PDF_COMBINER_SERVICE', 'http://10.88.22.231:2999'), '/') . '/lumen/invoke',

    /** Target slice saat Python callback ke Lumen (body kosong). */
    'invoke_controller' => env('PAYROLL_DISBURSEMENT_INVOKE_CONTROLLER', 'PaymentPayrollKaryawanController'),
    'invoke_function' => env('PAYROLL_DISBURSEMENT_INVOKE_FUNCTION', 'completePayrollDisbursement'),
    'invoke_async' => filter_var(env('PAYROLL_DISBURSEMENT_INVOKE_ASYNC', true), FILTER_VALIDATE_BOOLEAN),

    /** Header X-Worker-Secret ke python-service-isl (samakan dengan INVOKE_SECRET di Python). */
    'invoke_secret' => env('PAYROLL_DISBURSEMENT_INVOKE_SECRET', env('INVOKE_SECRET', '')),

    /** Legacy: POST /api/payroll-transfer/complete (opsional, manual / bank worker lain). */
    'callback_token' => env('PAYROLL_DISBURSEMENT_CALLBACK_TOKEN', ''),
    'callback_url' => env('PAYROLL_DISBURSEMENT_CALLBACK_URL', ''),

    'http_timeout_seconds' => (int) env('PAYROLL_DISBURSEMENT_HTTP_TIMEOUT', 15),

    /** Jika false, transfer payroll tidak memanggil Python (hanya simpan tgl_transfer). */
    'enabled' => filter_var(env('PAYROLL_DISBURSEMENT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

];
