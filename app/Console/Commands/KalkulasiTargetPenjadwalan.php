<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;

use App\Services\KalkulasiTargetPenjadwalanService;

class KalkulasiTargetPenjadwalan extends Command
{
    protected $signature = 'kalkulasitargetjadwal';
    protected $description = 'Calculate target scheduling with detailed logging';

    public function handle()
    {
        $startTime = microtime(true);

        $this->output->newLine();
        $this->info("  START KALKULASI TARGET PENJADWALAN  ");
        $this->info("  Time: " . Carbon::now()->toDateTimeString());

        // Run Phase 1
        (new KalkulasiTargetPenjadwalanService())->execute();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->output->newLine();
        $this->info("========================================================================");
        $this->info("  TARGET PENJADWALAN KALKULASINYA BERHASIL  ");
        $this->info("  Total Execution Time: " . number_format($duration, 2) . " seconds");
        $this->info("========================================================================");
    }
}
