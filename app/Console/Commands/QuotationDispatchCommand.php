<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationAutoDispatchQuery;
use App\Services\QuotationGenerate\QuotationGenerateProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class QuotationDispatchCommand extends Command
{
    protected $signature = 'quotationdispatch
                            {--limit= : Max penawaran pending to process}
                            {--batch= : Chunk size (default config: 100)}
                            {--item-pause= : Jeda antar setiap QT detik (default config: 5)}
                            {--chunk-pause= : Jeda antar chunk detik (default config: 0)}
                            {--dry-run : Hanya simulasi}
                            {--customer= : Filter id_pelanggan}
                            {--quotation= : Proses satu quotation id saja}';

    protected $description = 'Auto quotation step 2: render PDF, generate link/token, kirim email untuk penawaran AUTO yang pending';

    public function handle(): int
    {
        $pendingQuery = new QuotationAutoDispatchQuery();
        $processor = new QuotationGenerateProcessingService();

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $batchSize = max(1, (int) ($this->option('batch') ?: config('quotation_auto.generate.dispatch_chunk_size', 100)));
        $itemPauseSeconds = max(0, (int) ($this->option('item-pause') !== null && $this->option('item-pause') !== ''
            ? $this->option('item-pause')
            : config('quotation_auto.generate.dispatch_item_pause_seconds', 5)));
        $chunkPauseSeconds = max(0, (int) ($this->option('chunk-pause') !== null && $this->option('chunk-pause') !== ''
            ? $this->option('chunk-pause')
            : config('quotation_auto.generate.dispatch_chunk_pause_seconds', 0)));
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
        $this->line('Chunk size         : ' . $batchSize . ' QT (kelompok log)');
        $this->line('Jeda per QT        : ' . $itemPauseSeconds . ' detik');
        if ($chunkPauseSeconds > 0) {
            $this->line('Jeda antar chunk   : ' . $chunkPauseSeconds . ' detik');
        }
        if ($dryRun) {
            $this->line('Dry-run            : tanpa jeda');
        }
        $this->newLine();

        $pending = $pendingQuery->fetchPending($limit, $customerFilter, $quotationId);

        if ($pending->isEmpty()) {
            $this->warn('Tidak ada penawaran AUTO pending untuk di-dispatch.');

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        $chunks = $pending->chunk($batchSize)->values();
        $totalChunks = $chunks->count();
        $totalItems = $pending->count();
        $processedItems = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->line('--- Chunk ' . ($chunkIndex + 1) . '/' . $totalChunks . ' (' . $chunk->count() . ' QT) ---');

            /** @var Collection $chunk */
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

                $processedItems++;
                if (!$dryRun && $itemPauseSeconds > 0 && $processedItems < $totalItems) {
                    sleep($itemPauseSeconds);
                }
            }

            if (!$dryRun && $chunkPauseSeconds > 0 && $chunkIndex < $totalChunks - 1) {
                $this->line('Jeda antar chunk ' . $chunkPauseSeconds . ' detik...');
                sleep($chunkPauseSeconds);
            }
        }

        $this->newLine();
        $this->line('================ DISPATCH SUMMARY ================');
        $this->line('Processed (attempted) : ' . $pending->count());
        $this->line('Success               : ' . $success);
        $this->line('Failed                : ' . $failed);
        $this->line('==================================================');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
