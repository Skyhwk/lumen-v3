<?php

namespace App\Console\Commands;

use App\Helpers\WorkerInternalMailSync;
use App\Services\InternalMailService;
use Illuminate\Console\Command;

class SyncInternalMailCommand extends Command
{
    protected $signature = 'mail:sync-inbox {user_id? : ID karyawan} {--force : Sync ulang penuh folder inbox}';
    protected $description = 'Sync inbox internal mail dari IMAP ke index DB (bounded, aman untuk scheduler)';

    public function handle(): int
    {
        $userId = $this->argument('user_id');

        if ($userId) {
            $service = new InternalMailService((int) $userId);
            $result = $service->runBoundedSync('inbox', (bool) $this->option('force'), 45);
            $this->info('Sync selesai: ' . json_encode($result));

            return 0;
        }

        WorkerInternalMailSync::run();
        $this->info('WorkerInternalMailSync tick selesai');

        return 0;
    }
}
