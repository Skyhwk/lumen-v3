<?php

namespace App\Console\Commands;

use App\Services\CustomerServiceConversationService;
use Illuminate\Console\Command;

class CustomerServiceAutoCloseCommand extends Command
{
    protected $signature = 'cs:auto-close';

    protected $description = 'Tutup otomatis ticket CS waiting_customer yang melewati auto_close_at';

    public function handle()
    {
        $count = CustomerServiceConversationService::autoCloseExpiredTickets();

        if ($count === 0) {
            $this->info('Tidak ada ticket CS yang perlu ditutup otomatis.');
            return 0;
        }

        $this->info("Berhasil menutup otomatis {$count} ticket CS.");
        return 0;
    }
}
