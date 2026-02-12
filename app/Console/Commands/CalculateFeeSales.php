<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Carbon\Carbon;

use App\Services\FeeSalesMonthly;
use App\Services\FeeSalesMonthly2;

class CalculateFeeSales extends Command
{
    protected $signature = 'calculatefee:run {period?}';
    protected $description = 'Calculate fee sales with detailed logging';

    private $feeSalesMonthly;
    private $feeSalesMonthly2;

    public function __construct(FeeSalesMonthly $feeSalesMonthly, FeeSalesMonthly2 $feeSalesMonthly2)
    {
        parent::__construct();

        $this->feeSalesMonthly = $feeSalesMonthly;
        $this->feeSalesMonthly2 = $feeSalesMonthly2;
    }

    public function handle()
    {
        $startTime = microtime(true);
        $period = $this->argument('period') ?? null;

        $this->output->newLine();
        $this->info("  STARTING FEE SALES CALCULATION  ");
        $this->info("  Time: " . Carbon::now()->toDateTimeString());

        if ($period) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
                $this->error("Format periode tidak valid. Gunakan format tahun-bulan, ex: 2026-01");
                return 1;
            }
        }

        // Run Phase 1
        $this->feeSalesMonthly->run($period);

        // Run Phase 2
        $this->feeSalesMonthly2->run($period);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->output->newLine();
        $this->info("========================================================================");
        $this->info("  FEE SALES CALCULATED SUCCESSFULLY  ");
        $this->info("  Total Execution Time: " . number_format($duration, 2) . " seconds");
        $this->info("========================================================================");
    }
}
