<?php
namespace App\Console\Commands;

use App\Helpers\WorkerReassignNew;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AssignSales extends Command
{
    protected $signature   = 'reassignsales';
    protected $description = 'Run the scheduler every second (manual loop)';

    public function handle()
    {
        WorkerReassignNew::run();
    }
}
