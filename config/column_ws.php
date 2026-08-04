<?php

/**
 * MIN_COLUMN_WS_UDARA=1
 * MAX_COLUMN_WS_UDARA=23
 * MIN_COLUMN_WS_LINGKUNGAN=0
 * MAX_COLUMN_WS_LINGKUNGAN=18
 * MIN_COLUMN_WS_EMISI=0
 * MAX_COLUMN_WS_EMISI=11
 */
return [
    'ws_value_udara' => [
        'min' => env('MIN_COLUMN_WS_UDARA', 0),
        'max' => env('MAX_COLUMN_WS_UDARA', 22),
    ],
    'ws_value_lingkungan' => [
        'min' => env('MIN_COLUMN_WS_LINGKUNGAN', 0),
        'max' => env('MAX_COLUMN_WS_LINGKUNGAN', 18),
    ],
    'ws_value_emisi' => [
        'min' => env('MIN_COLUMN_WS_EMISI', 0),
        'max' => env('MAX_COLUMN_WS_EMISI', 11),
    ],
];