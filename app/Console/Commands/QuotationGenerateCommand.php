<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationGenerateDiscoveryService;
use App\Services\QuotationGenerate\QuotationGenerateEligibleCollector;
use App\Services\QuotationGenerate\QuotationGenerateProcessingService;
use App\Services\QuotationGenerate\SelectionTimeWindow;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class QuotationGenerateCommand extends Command
{
    protected $signature = 'quotation:generate
                            {--limit= : Max eligible rows to process}
                            {--batch=100 : Chunk size when iterating candidates}
                            {--dry-run : Hanya simulasi, tanpa copy/generate/email}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto quotation end-to-end (quotation:create + quotation:dispatch dalam satu perintah)';

    public function handle(): int
    {
        $timeWindow = SelectionTimeWindow::fromConfig();
        $discovery = new QuotationGenerateDiscoveryService();
        $collector = new QuotationGenerateEligibleCollector();
        $processor = new QuotationGenerateProcessingService();

        $quotationSince = $timeWindow->quotationSince();
        $orderSince = $timeWindow->orderSince();
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $batchSize = max(1, (int) ($this->option('batch') ?: 100));
        $dryRun = (bool) $this->option('dry-run');
        $customerFilter = $this->option('customer');

        $sendEmail = (bool) config('quotation_auto.generate.send_email');
        $emailTestMode = (bool) config('quotation_auto.generate.email_test_mode');
        $this->info('Quotation generate (create + dispatch)' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->line('Tip: pisah step → quotation:create lalu quotation:dispatch');
        $this->line('Send email         : ' . ($sendEmail ? 'ON' : 'OFF (remark — verifikasi copy/PDF dulu)'));
        if ($sendEmail && $emailTestMode) {
            $this->line('Email test mode    : ON → To ' . config('quotation_auto.generate.email_test_to') . ', CC/BCC kosong');
        }
        $this->line('Quotation since: ' . $quotationSince->toDateTimeString());
        $this->line('Order since: ' . $orderSince->toDateTimeString());
        $this->newLine();

        $eligible = $collector->collect($discovery, $quotationSince, $orderSince, $limit, $customerFilter);

        if ($eligible->isEmpty()) {
            $this->warn('Tidak ada kandidat ELIGIBLE untuk diproses.');
            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        $eligible->chunk($batchSize)->each(function (Collection $chunk) use ($processor, $dryRun, &$success, &$failed) {
            foreach ($chunk as $row) {
                try {
                    $result = $processor->processEligibleCandidate($row, $dryRun);
                    $success++;
                    $this->line('[OK] ' . $row->customer_id . ' | ' . $result['message']);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error('[FAIL] ' . $row->customer_id . ' | ' . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->line('================ GENERATE SUMMARY ================');
        $this->line('Processed (attempted) : ' . $eligible->count());
        $this->line('Success               : ' . $success);
        $this->line('Failed                : ' . $failed);
        $this->line('==================================================');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
