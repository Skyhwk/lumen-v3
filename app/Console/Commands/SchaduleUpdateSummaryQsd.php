<?php
namespace App\Console\Commands;

use App\Services\SummaryQSDServices;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SchaduleUpdateSummaryQsd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updatesummaryqsd:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Update Summary Qsd';

    public function handle()
    {
        try {
            printf("[ShaduleSummaryQsd] [%s] Start Running...", Carbon::now()->format('Y-m-d H:i:s'));
            for ($i = Carbon::now()->year; $i >= 2024; $i--) {
                printf("\n[ShaduleSummaryQsd] [%s] Running untuk tahun %d\n", Carbon::now()->format('Y-m-d H:i:s'), $i);

                (new SummaryQSDServices())->year($i)->run();
            }
            printf("\n[ShaduleSummaryQsd] [%s] Running done", Carbon::now()->format('Y-m-d H:i:s'));
        } catch (\Throwable $th) {
            Log::error('[ShaduleSummaryQsd] Error: ' . $th->getMessage());
        }
    }
}