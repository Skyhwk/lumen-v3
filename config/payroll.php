<?php

return [
    /*
    | Jabatan dengan hari kerja Senin–Sabtu (id master_jabatan, comma-separated).
    | Dipakai saat generate absensi / hari_kerja di rekap_masuk_kerja.
    */
    'jabatan_include_saturday' => array_values(array_filter(array_map(
        'intval',
        array_map('trim', explode(',', env('PAYROLL_JABATAN_INCLUDE_SATURDAY', '35,36,94')))
    ))),
];
