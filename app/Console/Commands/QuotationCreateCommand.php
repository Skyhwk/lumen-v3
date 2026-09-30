<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationGenerateDiscoveryService;
use App\Services\QuotationGenerate\QuotationGenerateEligibleCollector;
use App\Services\QuotationGenerate\QuotationGenerateProcessingService;
use App\Services\QuotationGenerate\SelectionTimeWindow;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class QuotationCreateCommand extends Command
{
    protected $signature = 'quotation:create
                            {--limit= : Max eligible rows to process}
                            {--batch=100 : Chunk size when iterating candidates}
                            {--dry-run : Hanya simulasi, tanpa copy}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto quotation step 1: copy penawaran dari kandidat discovery (approve + kode_promo AUTO, tanpa PDF/email)';

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

        $this->info('Quotation create (step 1 — copy saja)' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->line('Quotation since: ' . $quotationSince->toDateTimeString());
        $this->line('Order since: ' . $orderSince->toDateTimeString());
        $this->line('Langkah berikutnya setelah selesai: php artisan quotation:dispatch');
        $this->newLine();

        $eligible = $collector->collect($discovery, $quotationSince, $orderSince, $limit, $customerFilter);

        if ($eligible->isEmpty()) {
            $this->warn('Tidak ada kandidat ELIGIBLE untuk diproses.');

            return self::SUCCESS;
        }

        return $this->runBatch($eligible, $batchSize, $dryRun, $processor, 'createFromCandidate', 'CREATE');
    }

    private function runBatch(
        Collection $eligible,
        int $batchSize,
        bool $dryRun,
        QuotationGenerateProcessingService $processor,
        string $method,
        string $summaryLabel
    ): int {
        $success = 0;
        $failed = 0;

        $eligible->chunk($batchSize)->each(function (Collection $chunk) use ($processor, $dryRun, $method, &$success, &$failed) {
            foreach ($chunk as $row) {
                try {
                    $result = $processor->{$method}($row, $dryRun);
                    $success++;
                    $this->line('[OK] ' . $row->customer_id . ' | ' . $result['message']);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error('[FAIL] ' . $row->customer_id . ' | ' . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->line('================ ' . $summaryLabel . ' SUMMARY ================');
        $this->line('Processed (attempted) : ' . $eligible->count());
        $this->line('Success               : ' . $success);
        $this->line('Failed                : ' . $failed);
        $this->line('==================================================');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
