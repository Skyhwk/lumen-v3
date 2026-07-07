<?php

namespace App\Console\Commands;

use App\Services\LogTransactionQsdService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleLogTransactionQsd extends Command
{
    protected $signature = 'logTransactionQsd:run {--type=all : revenue, forecast, or all}';

    protected $description = 'Sync QSD revenue & forecast transaction logs';

    public function handle()
    {
        $type = strtolower($this->option('type') ?? 'all');

        try {
            printf("\n[LogTransactionQsd] [%s] Start...", date('Y-m-d H:i:s'));

            if ($type === 'revenue') {
                $count = LogTransactionQsdService::syncRevenue();
                printf("\n[LogTransactionQsd] Revenue logs inserted: %d", $count);
            } elseif ($type === 'forecast') {
                $count = LogTransactionQsdService::syncForecast();
                printf("\n[LogTransactionQsd] Forecast logs inserted: %d", $count);
            } else {
                $result = LogTransactionQsdService::run();
                printf("\n[LogTransactionQsd] Revenue logs: %d, Forecast logs: %d", $result['revenue_logs'], $result['forecast_logs']);
            }

            printf("\n[LogTransactionQsd] [%s] Done.", date('Y-m-d H:i:s'));
        } catch (\Throwable $th) {
            Log::error('[LogTransactionQsd] Error: ' . $th->getMessage());
            printf("\n[LogTransactionQsd] Error: %s", $th->getMessage());
        }
    }
}
