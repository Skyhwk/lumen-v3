<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationGenerateCampaign;
use App\Services\QuotationGenerate\QuotationGenerateDiscoveryService;
use App\Services\QuotationGenerate\QuotationGenerateEligibleCollector;
use App\Services\QuotationGenerate\QuotationGenerateProcessingService;
use App\Services\QuotationGenerate\SelectionTimeWindow;
use Database\Seeders\PromoPengujianAutoJanToJuneSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateJanToJuneCommand extends Command
{
    protected $signature = 'generateJanToJune
                            {--limit= : Max eligible rows to process}
                            {--batch= : Chunk size (default 100)}
                            {--item-pause= : Jeda antar setiap QT detik (default 5)}
                            {--chunk-pause= : Jeda antar chunk detik (default 0)}
                            {--dry-run : Hanya simulasi, tanpa copy/generate/email}
                            {--test : Tes 2 QT, email hanya ke harold@intilab.com (tanpa BCC)}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto quotation Jan–June (non-kontrak): copy QT, kode_promo Auto_Jan_to_June, sales dari master_pelanggan, PDF + email';

    public function handle(): int
    {
        QuotationGenerateCampaign::apply('jan_to_june');
        $this->applyTestMode();
        $this->ensurePromoPengujian();

        $timeWindow = SelectionTimeWindow::fromConfig();
        $discovery = new QuotationGenerateDiscoveryService();
        $collector = new QuotationGenerateEligibleCollector();
        $processor = new QuotationGenerateProcessingService();

        $quotationSince = $timeWindow->quotationSince();
        $orderSince = $timeWindow->orderSince();
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        if ($this->option('test') && $this->option('limit') === null) {
            $limit = 2;
        }
        $batchSize = max(1, (int) ($this->option('batch') ?: config('quotation_auto.generate.dispatch_chunk_size', 100)));
        $itemPauseSeconds = max(0, (int) ($this->option('item-pause') !== null && $this->option('item-pause') !== ''
            ? $this->option('item-pause')
            : config('quotation_auto.generate.dispatch_item_pause_seconds', 5)));
        $chunkPauseSeconds = max(0, (int) ($this->option('chunk-pause') !== null && $this->option('chunk-pause') !== ''
            ? $this->option('chunk-pause')
            : config('quotation_auto.generate.dispatch_chunk_pause_seconds', 0)));
        $dryRun = (bool) $this->option('dry-run');
        $customerFilter = $this->option('customer');

        $sendEmail = (bool) config('quotation_auto.generate.send_email');
        $emailTestMode = (bool) config('quotation_auto.generate.email_test_mode');
        $marker = (string) config('quotation_auto.generate.kode_promo_marker');
        $fallbackSalesId = (int) config('quotation_auto.generate.fallback_sales_id', 1068);

        $this->info('Generate Jan to June (non-kontrak)' . ($dryRun ? ' (DRY RUN)' : '') . ($this->option('test') ? ' (TEST)' : ''));
        $this->line('Kode promo         : ' . $marker);
        $this->line('Sales              : master_pelanggan.sales_id, fallback id ' . $fallbackSalesId . ' (Asti Susilawati)');
        $this->line('Send email         : ' . ($sendEmail ? 'ON' : 'OFF (set QUOTATION_GENERATE_SEND_EMAIL=true)'));
        if ($sendEmail && $emailTestMode) {
            $this->line('Email test mode    : ON → To ' . config('quotation_auto.generate.email_test_to') . ', BCC kosong (tanpa CC)');
        }
        $this->line('Quotation since    : ' . $quotationSince->toDateTimeString());
        $this->line('Order since        : ' . $orderSince->toDateTimeString());
        $this->line('Chunk / jeda       : ' . $batchSize . ' QT per kelompok, ' . $itemPauseSeconds . ' detik antar QT');
        $this->newLine();

        $eligible = $collector->collect($discovery, $quotationSince, $orderSince, $limit, $customerFilter);

        if ($eligible->isEmpty()) {
            $this->warn('Tidak ada kandidat ELIGIBLE untuk diproses.');

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;
        $chunks = $eligible->chunk($batchSize)->values();
        $totalChunks = $chunks->count();
        $totalItems = $eligible->count();
        $processedItems = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->line('--- Chunk ' . ($chunkIndex + 1) . '/' . $totalChunks . ' (' . $chunk->count() . ' QT) ---');

            foreach ($chunk as $row) {
                try {
                    $result = $processor->processEligibleCandidate($row, $dryRun);
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

            if (!$dryRun && $chunkPauseSeconds > 0 && $chunkIndex < $totalChunks - 1) {
                $this->line('Jeda antar chunk ' . $chunkPauseSeconds . ' detik...');
                sleep($chunkPauseSeconds);
            }
        }

        $this->newLine();
        $this->line('================ JAN TO JUNE SUMMARY ================');
        $this->line('Processed (attempted) : ' . $eligible->count());
        $this->line('Success               : ' . $success);
        $this->line('Failed                : ' . $failed);
        $this->line('=====================================================');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function applyTestMode(): void
    {
        if (!$this->option('test')) {
            return;
        }

        config([
            'quotation_auto.generate.send_email' => true,
            'quotation_auto.generate.email_test_mode' => true,
            'quotation_auto.generate.email_test_to' => 'harold@intilab.com',
        ]);
    }

    private function ensurePromoPengujian(): void
    {
        $marker = (string) config('quotation_auto.generate.kode_promo_marker', 'Auto_Jan_to_June');
        if (!Schema::hasTable('promo_pengujian')) {
            throw new \RuntimeException('Tabel promo_pengujian belum ada. Jalankan migration dulu.');
        }

        if (DB::table('promo_pengujian')->where('kode_promo', $marker)->exists()) {
            $this->line('Promo pengujian   : ' . $marker . ' sudah ada di DB');
            return;
        }

        $this->info('Promo pengujian ' . $marker . ' belum ada. Menjalankan seeder...');
        $seeder = new PromoPengujianAutoJanToJuneSeeder();
        $seeder->setCommand($this);
        $seeder->run();
    }
}
