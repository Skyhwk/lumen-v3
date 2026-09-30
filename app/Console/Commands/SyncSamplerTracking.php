<?php

namespace App\Console\Commands;

use App\Services\SamplerTrackingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncSamplerTracking extends Command
{
    protected $signature = 'sampler-tracking:sync {--date=}';
    protected $description = 'Reconcile all sampler teams and memberships against active schedules';

    public function handle(SamplerTrackingService $service)
    {
        $date = $this->option('date') ?: Carbon::now('Asia/Jakarta')->toDateString();
        $sessions = $service->sync($date);
        $this->info('Tracking synchronized: ' . $date . ' (' . $sessions->count() . ' sessions)');
        return 0;
    }
}
