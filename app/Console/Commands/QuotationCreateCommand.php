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
                            {--item-pause= : Jeda antar setiap copy detik (default config: 1)}
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
        $itemPauseSeconds = max(0, (int) ($this->option('item-pause') !== null && $this->option('item-pause') !== ''
            ? $this->option('item-pause')
            : config('quotation_auto.generate.create_item_pause_seconds', 1)));
        $dryRun = (bool) $this->option('dry-run');
        $customerFilter = $this->option('customer');

        $this->info('Quotation create (step 1 — copy saja)' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->line('Quotation since: ' . $quotationSince->toDateTimeString());
        $this->line('Order since: ' . $orderSince->toDateTimeString());
        $this->line('Jeda per copy      : ' . $itemPauseSeconds . ' detik' . ($dryRun ? ' (dry-run: tanpa jeda)' : ''));
        $this->line('Langkah berikutnya setelah selesai: php artisan quotation:dispatch');
        $this->newLine();

        $eligible = $collector->collect($discovery, $quotationSince, $orderSince, $limit, $customerFilter);

        if ($eligible->isEmpty()) {
            $this->warn('Tidak ada kandidat ELIGIBLE untuk diproses.');

            return self::SUCCESS;
        }

        return $this->runBatch($eligible, $batchSize, $itemPauseSeconds, $dryRun, $processor, 'CREATE');
    }

    private function runBatch(
        Collection $eligible,
        int $batchSize,
        int $itemPauseSeconds,
        bool $dryRun,
        QuotationGenerateProcessingService $processor,
        string $summaryLabel
    ): int {
        $success = 0;
        $failed = 0;
        $totalItems = $eligible->count();
        $processedItems = 0;

        $eligible->chunk($batchSize)->each(function (Collection $chunk) use (
            $processor,
            $dryRun,
            $itemPauseSeconds,
            $totalItems,
            &$processedItems,
            &$success,
            &$failed
        ) {
            foreach ($chunk as $row) {
                try {
                    $result = $processor->createFromCandidate($row, $dryRun);
                    $success++;
                    $this->line('[OK] ' . $row->customer_id . ' | ' . $result['message']);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error('[FAIL] ' . $row->customer_id . ' | ' . $e->getMessage());
                }

                $processedItems++;
                if (!$dryRun && $itemPauseSeconds > 0 && $processedItems < $totalItems) {
                    sleep($itemPauseSeconds);
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
