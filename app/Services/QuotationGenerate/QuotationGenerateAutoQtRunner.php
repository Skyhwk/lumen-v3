<?php

namespace App\Services\QuotationGenerate;

use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotationGenerateAutoQtRunner
{
    /**
     * @param  array{month?:string,test?:bool,dry_run?:bool,limit?:int|null,customer?:string|null,batch?:int|null,item_pause?:int|null,chunk_pause?:int|null}  $options
     * @param  callable|null  $output
     * @return array{success:bool,processed:int,success_count:int,failed_count:int,test:bool,dry_run:bool,month:string,logs:array<int,string>}
     */
    public function run(array $options = [], $output = null): array
    {
        $monthInput = (string) ($options['month'] ?? '');
        if ($monthInput === '') {
            throw new \InvalidArgumentException('Bulan target wajib diisi. Contoh: --desember atau --12-2026');
        }

        $window = QuotationAutoQtMonth::parse($monthInput);
        $test = !empty($options['test']);
        $dryRun = !empty($options['dry_run']);
        $limit = array_key_exists('limit', $options) && $options['limit'] !== null ? (int) $options['limit'] : null;
        if ($test && $limit === null) {
            $limit = 2;
        }

        QuotationGenerateCampaign::apply('auto_qt');
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

        $this->ensurePromoPengujian($window, $write);

        $discovery = new QuotationGenerateDiscoveryService();
        $processor = new QuotationGenerateProcessingService();

        $batchSize = max(1, (int) ($options['batch'] ?? config('quotation_auto.generate.dispatch_chunk_size', 100)));
        $itemPauseSeconds = max(0, (int) ($options['item_pause'] ?? config('quotation_auto.generate.dispatch_item_pause_seconds', 5)));
        $chunkPauseSeconds = max(0, (int) ($options['chunk_pause'] ?? config('quotation_auto.generate.dispatch_chunk_pause_seconds', 0)));
        $customerFilter = isset($options['customer']) && $options['customer'] !== '' ? (string) $options['customer'] : null;

        $sendEmail = (bool) config('quotation_auto.generate.send_email');
        $emailTestMode = (bool) config('quotation_auto.generate.email_test_mode');
        $marker = (string) config('quotation_auto.generate.kode_promo_marker');
        $fallbackSalesId = (int) config('quotation_auto.generate.fallback_sales_id', 1068);

        $write('Generate Auto QT (non-kontrak) ' . $window->label() . ($dryRun ? ' (DRY RUN)' : '') . ($test ? ' (TEST)' : ''), 'info');
        $write('Kode promo         : ' . $marker . ' (promo_id dari promo_pengujian)');
        $write('Sales              : master_pelanggan.sales_id, fallback id ' . $fallbackSalesId . ' (Asti Susilawati)');
        $write('Send email         : ' . ($sendEmail ? 'ON' : 'OFF'));
        if ($sendEmail && $emailTestMode) {
            $write('Email test mode    : ON → To ' . config('quotation_auto.generate.email_test_to') . ', BCC kosong');
        }
        $write('Window order skip  : ' . $window->orderFrom()->toDateTimeString() . ' s/d ' . $window->orderUntil()->copy()->subSecond()->toDateTimeString());
        $write('Syarat             : punya QT + tidak ada order di window 6 bulan');
        $write('Sampling           : dihitung ulang pakai nominal terbaru');
        $write('Diskon QT lama     : dihapus semua');
        $write('Alur               : build semua → render semua → email semua');

        $eligible = $discovery->fetchEligibleHasQuotationNoRecentOrder(
            $window->orderFrom(),
            $window->orderUntil(),
            $limit
        );
        if ($customerFilter !== null) {
            $eligible = $eligible->where('customer_id', $customerFilter)->values();
        }

        if ($eligible->isEmpty()) {
            $write('Tidak ada kandidat ELIGIBLE untuk diproses.', 'warn');

            return [
                'success' => true,
                'processed' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'test' => $test,
                'dry_run' => $dryRun,
                'month' => $window->token(),
                'logs' => $logs,
            ];
        }

        $created = [];
        $rendered = [];
        $createFailed = 0;
        $renderFailed = 0;
        $emailFailed = 0;
        $emailSuccess = 0;

        $write('=== Fase 1/3: build data (' . $eligible->count() . ' QT) ===', 'info');
        $this->eachPaused($eligible, $batchSize, $itemPauseSeconds, $chunkPauseSeconds, $dryRun, function ($row) use ($processor, $dryRun, $write, &$created, &$createFailed) {
            try {
                $result = $processor->createFromCandidate($row, $dryRun);
                $created[] = [
                    'customer_id' => $row->customer_id,
                    'source_quotation_id' => (int) $result['source_quotation_id'],
                    'new_quotation_id' => $result['new_quotation_id'] ?? null,
                    'new_no_document' => $result['new_no_document'] ?? null,
                ];
                $write('[BUILD OK] ' . $row->customer_id . ' | ' . $result['message']);
            } catch (\Throwable $e) {
                $createFailed++;
                $write('[BUILD FAIL] ' . $row->customer_id . ' | ' . $e->getMessage(), 'error');
            }
        });

        $write('=== Fase 2/3: render PDF (' . count($created) . ' QT) ===', 'info');
        $this->eachPaused($created, $batchSize, $itemPauseSeconds, $chunkPauseSeconds, $dryRun, function ($item) use ($processor, $dryRun, $write, &$rendered, &$renderFailed) {
            try {
                if ($dryRun) {
                    $rendered[] = $item;
                    $write('[RENDER OK] ' . $item['customer_id'] . ' | DRY RUN: render PDF + link dari source ' . $item['source_quotation_id']);
                    return;
                }

                $quotation = QuotationNonKontrak::findOrFail((int) $item['new_quotation_id']);
                $result = $processor->renderQuotation($quotation, false, (int) $item['source_quotation_id']);
                $rendered[] = $item;
                $write('[RENDER OK] ' . $item['customer_id'] . ' | ' . $result['message']);
            } catch (\Throwable $e) {
                $renderFailed++;
                $write('[RENDER FAIL] ' . $item['customer_id'] . ' | ' . $e->getMessage(), 'error');
            }
        });

        $write('=== Fase 3/3: email (' . count($rendered) . ' QT) ===', 'info');
        $this->eachPaused($rendered, $batchSize, $itemPauseSeconds, $chunkPauseSeconds, $dryRun, function ($item) use ($processor, $dryRun, $write, &$emailSuccess, &$emailFailed) {
            try {
                if ($dryRun) {
                    $emailSuccess++;
                    $write('[EMAIL OK] ' . $item['customer_id'] . ' | DRY RUN: email setelah semua PDF siap');
                    return;
                }

                $quotation = QuotationNonKontrak::findOrFail((int) $item['new_quotation_id']);
                $result = $processor->emailQuotation($quotation, false, (int) $item['source_quotation_id']);
                $emailSuccess++;
                $write('[EMAIL OK] ' . $item['customer_id'] . ' | ' . $result['message']);
            } catch (\Throwable $e) {
                $emailFailed++;
                $write('[EMAIL FAIL] ' . $item['customer_id'] . ' | ' . $e->getMessage(), 'error');
            }
        });

        $failed = $createFailed + $renderFailed + $emailFailed;
        $success = $emailSuccess;
        $write(
            'Processed : ' . $eligible->count()
            . ', built : ' . count($created)
            . ', rendered : ' . count($rendered)
            . ', emailed : ' . $emailSuccess
            . ', failed : ' . $failed
            . ' (build ' . $createFailed . ' / render ' . $renderFailed . ' / email ' . $emailFailed . ')',
            'info'
        );

        return [
            'success' => $failed === 0,
            'processed' => $eligible->count(),
            'success_count' => $success,
            'failed_count' => $failed,
            'test' => $test,
            'dry_run' => $dryRun,
            'month' => $window->token(),
            'logs' => $logs,
        ];
    }

    private function ensurePromoPengujian(QuotationAutoQtMonth $window, $write): void
    {
        if (!Schema::hasTable('promo_pengujian')) {
            throw new \RuntimeException('Tabel promo_pengujian belum ada. Jalankan migration dulu.');
        }

        $kodePromo = $window->promoCode();
        $namaDiskon = $window->promoName();
        config(['quotation_auto.generate.kode_promo_marker' => $kodePromo]);

        $existing = DB::table('promo_pengujian')->where('kode_promo', $kodePromo)->first();
        if ($existing) {
            $write('Promo pengujian   : ' . $kodePromo . ' sudah ada (id ' . $existing->id . ')');
            return;
        }

        $now = Carbon::now();
        $id = DB::table('promo_pengujian')->insertGetId([
            'kode_promo' => $kodePromo,
            'metode' => 'labeling',
            'nama_diskon' => $namaDiskon,
            'konfigurasi' => json_encode([]),
            'status' => 'aktif',
            'created_by' => 'generateAutoQt',
            'updated_by' => 'generateAutoQt',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $write('Promo pengujian ' . $kodePromo . ' dibuat (id ' . $id . ', labeling).', 'info');
    }

    private function eachPaused($items, int $batchSize, int $itemPauseSeconds, int $chunkPauseSeconds, bool $dryRun, callable $handler): void
    {
        $collection = collect($items)->values();
        if ($collection->isEmpty()) {
            return;
        }

        $chunks = $collection->chunk($batchSize)->values();
        $totalItems = $collection->count();
        $processedItems = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $item) {
                $handler($item);
                $processedItems++;
                if (!$dryRun && $itemPauseSeconds > 0 && $processedItems < $totalItems) {
                    sleep($itemPauseSeconds);
                }
            }

            if (!$dryRun && $chunkPauseSeconds > 0 && $chunkIndex < $chunks->count() - 1) {
                sleep($chunkPauseSeconds);
            }
        }
    }
}
