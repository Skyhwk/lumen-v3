<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class HourlyLogRequest
{
    public function __invoke(array $config)
    {
        $date = date('Y-m-d');
        $hour = date('H');

        $path = storage_path("log_request/{$date}");

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $file = "{$path}/request-jam-{$hour}.log";

        return new Logger('log_request', [
            new StreamHandler($file, Logger::INFO),
        ]);
    }
}
