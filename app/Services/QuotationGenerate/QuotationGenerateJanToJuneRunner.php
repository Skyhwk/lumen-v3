<?php

namespace App\Services\QuotationGenerate;

use Database\Seeders\PromoPengujianAutoJanToJuneSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotationGenerateJanToJuneRunner
{
    /**
     * @param  array{test?:bool,dry_run?:bool,limit?:int|null,customer?:string|null,batch?:int|null,item_pause?:int|null,chunk_pause?:int|null}  $options
     * @param  callable|null  $output  function (string $line, string $level): void
     * @return array{success:bool,processed:int,success_count:int,failed_count:int,test:bool,dry_run:bool,logs:array<int,string>}
     */
    public function run(array $options = [], $output = null): array
    {
        $test = !empty($options['test']);
        $dryRun = !empty($options['dry_run']);
        $limit = array_key_exists('limit', $options) && $options['limit'] !== null ? (int) $options['limit'] : null;
        if ($test && $limit === null) {
            $limit = 2;
        }

        QuotationGenerateCampaign::apply('jan_to_june');
        if ($test) {
            config([
                'quotation_auto.generate.send_email' => true,
                'quotation_auto.generate.email_test_mode' => true,
                'quotation_auto.generate.email_test_to' => 'harold@intilab.com',
            ]);
        }

        $logs = [];
        $write = function ($line, $level = 'line') use ($output, &$logs) {
            $logs[] = $line;
            if (is_callable($output)) {
                $output($line, $level);
            }
        };

        $this->ensurePromoPengujian($write);

        $timeWindow = SelectionTimeWindow::fromConfig();
        $discovery = new QuotationGenerateDiscoveryService();
        $collector = new QuotationGenerateEligibleCollector();
        $processor = new QuotationGenerateProcessingService();

        $quotationSince = $timeWindow->quotationSince();
        $orderSince = $timeWindow->orderSince();
        $batchSize = max(1, (int) ($options['batch'] ?? config('quotation_auto.generate.dispatch_chunk_size', 100)));
        $itemPauseSeconds = max(0, (int) ($options['item_pause'] ?? config('quotation_auto.generate.dispatch_item_pause_seconds', 5)));
        $chunkPauseSeconds = max(0, (int) ($options['chunk_pause'] ?? config('quotation_auto.generate.dispatch_chunk_pause_seconds', 0)));
        $customerFilter = isset($options['customer']) && $options['customer'] !== '' ? (string) $options['customer'] : null;

        $sendEmail = (bool) config('quotation_auto.generate.send_email');
        $emailTestMode = (bool) config('quotation_auto.generate.email_test_mode');
        $marker = (string) config('quotation_auto.generate.kode_promo_marker');
        $fallbackSalesId = (int) config('quotation_auto.generate.fallback_sales_id', 1068);

        $write('Generate Jan to June (non-kontrak)' . ($dryRun ? ' (DRY RUN)' : '') . ($test ? ' (TEST)' : ''), 'info');
        $write('Kode promo         : ' . $marker);
        $write('Sales              : master_pelanggan.sales_id, fallback id ' . $fallbackSalesId . ' (Asti Susilawati)');
        $write('Send email         : ' . ($sendEmail ? 'ON' : 'OFF'));
        if ($sendEmail && $emailTestMode) {
            $write('Email test mode    : ON → To ' . config('quotation_auto.generate.email_test_to') . ', BCC kosong');
        }
        $write('Quotation since    : ' . $quotationSince->toDateTimeString());
        $write('Order since        : ' . $orderSince->toDateTimeString());

        $eligible = $collector->collect($discovery, $quotationSince, $orderSince, $limit, $customerFilter);
        if ($eligible->isEmpty()) {
            $write('Tidak ada kandidat ELIGIBLE untuk diproses.', 'warn');

            return [
                'success' => true,
                'processed' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'test' => $test,
                'dry_run' => $dryRun,
                'logs' => $logs,
            ];
        }

        $success = 0;
        $failed = 0;
        $chunks = $eligible->chunk($batchSize)->values();
        $totalChunks = $chunks->count();
        $totalItems = $eligible->count();
        $processedItems = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $write('--- Chunk ' . ($chunkIndex + 1) . '/' . $totalChunks . ' (' . $chunk->count() . ' QT) ---');
            foreach ($chunk as $row) {
                try {
                    $result = $processor->processEligibleCandidate($row, $dryRun);
                    $success++;
                    $write('[OK] ' . $row->customer_id . ' | ' . $result['message']);
                } catch (\Throwable $e) {
                    $failed++;
                    $write('[FAIL] ' . $row->customer_id . ' | ' . $e->getMessage(), 'error');
                }

                $processedItems++;
                if (!$dryRun && $itemPauseSeconds > 0 && $processedItems < $totalItems) {
                    sleep($itemPauseSeconds);
                }
            }

            if (!$dryRun && $chunkPauseSeconds > 0 && $chunkIndex < $totalChunks - 1) {
                sleep($chunkPauseSeconds);
            }
        }

        $write('Processed : ' . $eligible->count() . ', success : ' . $success . ', failed : ' . $failed, 'info');

        return [
            'success' => $failed === 0,
            'processed' => $eligible->count(),
            'success_count' => $success,
            'failed_count' => $failed,
            'test' => $test,
            'dry_run' => $dryRun,
            'logs' => $logs,
        ];
    }

    private function ensurePromoPengujian($write): void
    {
        $marker = (string) config('quotation_auto.generate.kode_promo_marker', 'Auto_Jan_to_June');
        if (!Schema::hasTable('promo_pengujian')) {
            throw new \RuntimeException('Tabel promo_pengujian belum ada. Jalankan migration dulu.');
        }

        if (DB::table('promo_pengujian')->where('kode_promo', $marker)->exists()) {
            $write('Promo pengujian   : ' . $marker . ' sudah ada di DB');
            return;
        }

        $write('Promo pengujian ' . $marker . ' belum ada. Menjalankan seeder...', 'info');
        $seeder = new PromoPengujianAutoJanToJuneSeeder();
        $seeder->run();
        $write('Promo pengujian ' . $marker . ' berhasil dibuat.', 'info');
    }
}
