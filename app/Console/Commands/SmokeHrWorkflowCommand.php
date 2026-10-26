<?php

namespace App\Console\Commands;

use App\Services\Hr\HrTableMode;
use Illuminate\Console\Command;

class SmokeHrWorkflowCommand extends Command
{
    protected $signature = 'greatday:smoke-hr
                            {--fix : Perbaiki mismatch status via PortalHrSync}
                            {--recent-days= : Batasi parity check ke pengajuan N hari terakhir}';

    protected $description = 'Smoke test migrasi HR: count (M3) + parity status legacy vs hr_*';

    public function handle(): int
    {
        $this->info('=== Greatday HR smoke ===');
        $this->line('use_legacy_hr_tables: ' . (HrTableMode::usesLegacyHrTables() ? 'true (greatday masih legacy)' : 'false (greatday pakai hr_*)'));
        $this->line('dual_write_legacy_hr: ' . (HrTableMode::dualWriteLegacy() ? 'true' : 'false'));
        $this->line('freeze_legacy_hr_writes: ' . (HrTableMode::freezeLegacyHrWrites() ? 'true' : 'false'));
        $this->newLine();

        $exit = $this->call('greatday:verify-hr-migration');

        $parityArgs = ['--limit' => 10];
        if ($this->option('fix')) {
            $parityArgs['--fix'] = true;
        }
        if ($this->option('recent-days') !== null && $this->option('recent-days') !== '') {
            $parityArgs['--recent-days'] = $this->option('recent-days');
        }

        $parityExit = $this->call('greatday:verify-hr-status-parity', $parityArgs);

        if ($exit === 0 && $parityExit === 0) {
            $this->info('Smoke HR: PASS');

            return 0;
        }

        $this->error('Smoke HR: FAIL — lihat output di atas.');

        return 1;
    }
}
