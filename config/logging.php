<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Lumen Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'update_kpi_sales' => [
            'driver' => 'daily',
            'path' => storage_path('logs/update_kpi_sales/log.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'release_apps_fdl' => [
            'driver' => 'daily',
            'path' => storage_path('logs/release_apps_fdl/log.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'release_frontend' => [
            'driver' => 'daily',
            'path' => storage_path('logs/release_frontend/log.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'release_backend' => [
            'driver' => 'daily',
            'path' => storage_path('logs/release_backend/log.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'render' => [
            'driver' => 'daily',
            'path' => storage_path('logs/render/render.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'quotation' => [
            'driver' => 'daily',
            'path' => storage_path('logs/create_quotation/quotation.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'custom_email_log' => [
            'driver' => 'single',
            'path' => storage_path('logs/custom_email.log'),
            'level' => 'debug',
        ],

        'perubahan_no_sampel' => [
            'driver' => 'daily',
            'path' => storage_path('logs/perubahan_sampel/perubahan_sampel.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'perubahan_tanggal' => [
            'driver' => 'daily',
            'path' => storage_path('logs/perubahan_tanggal/log.log'),
            'level' => 'info',
            'days' => 30,
        ],

        'perubahan_jadwal' => [
            'driver' => 'daily',
            'path' => storage_path('logs/perubahan_jadwal/log.log'),
            'level' => 'info',
            'days' => 30,
        ],

        'send_mqtt' => [
            'driver' => 'daily',
            'path' => storage_path('logs/send_mqtt/logs.log'),
            'level' => 'info',
            'days' => 3,
        ],

        'reassign_customer' => [
            'driver' => 'daily',
            'path' => storage_path('logs/reassign_customer/reassign_customer.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'summary_qsd' => [
            'driver' => 'daily',
            'path' => storage_path('logs/summary_qsd/summary_qsd.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'reorder' => [
            'driver' => 'daily',
            'path' => storage_path('logs/reorder/reorder.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'analyst_approve' => [
            'driver' => 'daily',
            'path' => storage_path('logs/analyst_approve/analyst_approve.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'print_lhp' => [
            'driver' => 'daily',
            'path' => storage_path('logs/print_lhp/print_lhp.log'),
            'level' => 'info',
            'days' => 7,
        ],

        'utf8' => [
            'driver' => 'daily',
            'path' => storage_path('logs/utf8/sanitizer.log'),
            'level' => 'info',
            'days' => 3,
        ],

        'transaction' => [
            'driver' => 'daily',
            'path' => storage_path('logs/transaction/transaction.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'summary_parameter' => [
            'driver' => 'daily',
            'path' => storage_path('logs/summary_parameter/logs.log'),
            'level' => 'info',
            'days' => 14,
        ],

        'delete' => [
            'driver' => 'daily',
            'path' => storage_path('logs/delete/delete.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'forced_update_sampel' => [
            'driver' => 'daily',  
            'path' => storage_path('logs/forced_update_sampel/forced_update_sampel.log'),
            'level' => 'info',
            'days' => 90,
        ],

        // 'log_request' => [
        //     'driver' => 'daily',
        //     'path' => storage_path('log_request/request.log'),
        //     'level' => 'info',
        //     'days' => 10,
        // ],

        'log_request' => [
            'driver' => 'custom',
            'via' => App\Logging\HourlyLogRequest::class,
            'level' => 'info'
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],
    ],

];
