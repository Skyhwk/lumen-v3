<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;

use App\Services\FeeSalesDaily;

class SummaryFeeSales extends Command
{
    protected $signature = 'summarizefeesales';
    protected $description = 'Calculate summary fee sales with detailed logging';

    public function handle()
    {
        $startTime = microtime(true);

        $this->output->newLine();
        $this->info("  START SUMMARIZING FEE SALES  ");
        $this->info("  Time: " . Carbon::now()->toDateTimeString());

        // Run Phase 1
        (new FeeSalesDaily())->run();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->output->newLine();
        $this->info("========================================================================");
        $this->info("  FEE SALES SUMMARIZED SUCCESSFULLY  ");
        $this->info("  Total Execution Time: " . number_format($duration, 2) . " seconds");
        $this->info("========================================================================");
    }
}
