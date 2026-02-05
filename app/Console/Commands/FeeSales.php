<?php
namespace App\Console\Commands;

use App\Helpers\WorkerFeeSales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Services\FeeSalesMonthly;

class FeeSales extends Command
{
    protected $signature   = 'calculatefeesales';
    protected $description = 'Run the scheduler every second (manual loop)';

    public function handle()
    {
        (new FeeSalesMonthly())->run();
        // WorkerFeeSales::run();
    }
}
