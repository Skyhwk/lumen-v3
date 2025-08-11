<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RequestLog;
use Carbon\Carbon;

class CleanOldRequestLogs extends Command
{
    protected $signature = 'logs:clean';

    protected $description = 'Delete request logs older than 3 months';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $date = Carbon::now()->subMonths(3);
        $deletedRows = RequestLog::where('date_req', '<', $date)->delete();

        $this->info("Deleted $deletedRows request logs older than 3 months.");
    }
}