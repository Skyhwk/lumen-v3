<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationAutoDispatchQuery;
use App\Services\QuotationGenerate\QuotationGenerateProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class QuotationDispatchCommand extends Command
{
    protected $signature = 'quotation:dispatch
                            {--limit= : Max penawaran pending to process}
                            {--batch=100 : Chunk size}
                            {--dry-run : Hanya simulasi}
                            {--customer= : Filter id_pelanggan}
                            {--quotation= : Proses satu quotation id saja}';

    protected $description = 'Auto quotation step 2: render PDF, generate link/token, kirim email untuk penawaran AUTO yang pending';

    public function handle(): int
    {
        $pendingQuery = new QuotationAutoDispatchQuery();
        $processor = new QuotationGenerateProcessingService();

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $batchSize = max(1, (int) ($this->option('batch') ?: 100));
        $dryRun = (bool) $this->option('dry-run');
        $customerFilter = $this->option('customer');
        $quotationId = $this->option('quotation') !== null ? (int) $this->option('quotation') : null;

        $sendEmail = (bool) config('quotation_auto.generate.send_email');
        $emailTestMode = (bool) config('quotation_auto.generate.email_test_mode');

        $this->info('Quotation dispatch (step 2 — PDF + link + email)' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->line('Send email         : ' . ($sendEmail ? 'ON' : 'OFF'));
        if ($sendEmail && $emailTestMode) {
            $this->line('Email test mode    : ON → To ' . config('quotation_auto.generate.email_test_to') . ', CC/BCC kosong');
        }
        $this->newLine();

        $pending = $pendingQuery->fetchPending($limit, $customerFilter, $quotationId);

        if ($pending->isEmpty()) {
            $this->warn('Tidak ada penawaran AUTO pending untuk di-dispatch.');

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        $pending->chunk($batchSize)->each(function (Collection $chunk) use ($processor, $dryRun, &$success, &$failed) {
            foreach ($chunk as $quotation) {
                $customerId = (string) $quotation->pelanggan_ID;
                try {
                    $result = $processor->dispatchQuotation($quotation, $dryRun);
                    $success++;
                    $this->line('[OK] ' . $customerId . ' | ' . $result['message']);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error('[FAIL] ' . $customerId . ' | ' . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->line('================ DISPATCH SUMMARY ================');
        $this->line('Processed (attempted) : ' . $pending->count());
        $this->line('Success               : ' . $success);
        $this->line('Failed                : ' . $failed);
        $this->line('==================================================');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
