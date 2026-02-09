<?php
namespace App\Console\Commands;

use App\Helpers\WorkerFeeSales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Services\FeeSalesMonthly2;

class FeeSales2 extends Command
{
    protected $signature   = 'calculatefeesales2';
    protected $description = 'Run the scheduler every second (manual loop)';

    public function handle()
    {
        (new FeeSalesMonthly2())->run();
        // WorkerFeeSales::run();
    }
}
