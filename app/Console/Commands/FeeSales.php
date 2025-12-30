<?php
namespace App\Console\Commands;

use App\Helpers\WorkerFeeSales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FeeSales extends Command
{
    protected $signature   = 'calculate-fee-sales';
    protected $description = 'Run the scheduler every second (manual loop)';

    public function handle()
    {
        WorkerFeeSales::run();
    }
}
