<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Services\UpdateForecastSPService;

class ScheduleUpdateForecastSP extends Command
{
    protected $signature = 'updateForecastSP';
    protected $description = 'Update Forecast SP Data';

    public function handle()
    {
        UpdateForecastSPService::run();
    }
}
