<?php

return [
    /*
    | Jabatan Senin–Sabtu (sampler / OB / OG — id master_jabatan, comma-separated).
    | Shift off/libur di jadwal tidak dihitung wajib masuk (tidak alpa).
    */
    'jabatan_include_saturday' => array_values(array_filter(array_map(
        'intval',
        array_map('trim', explode(',', env('PAYROLL_JABATAN_INCLUDE_SATURDAY', '35,36,94')))
    ))),
];
