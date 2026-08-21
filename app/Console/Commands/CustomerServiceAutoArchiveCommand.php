<?php

namespace App\Console\Commands;

use App\Services\CustomerServiceConversationService;
use Illuminate\Console\Command;

class CustomerServiceAutoArchiveCommand extends Command
{
    protected $signature = 'cs:auto-archive';

    protected $description = 'Arsip ticket CS closed yang sudah melewati archived_at';

    public function handle()
    {
        $count = CustomerServiceConversationService::autoArchiveExpiredTickets();

        if ($count === 0) {
            $this->info('Tidak ada ticket CS yang perlu diarsipkan.');
            return 0;
        }

        $this->info("Berhasil mengarsipkan {$count} ticket CS.");
        return 0;
    }
}
