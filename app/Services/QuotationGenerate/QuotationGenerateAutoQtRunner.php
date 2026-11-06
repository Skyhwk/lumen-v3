<?php

namespace App\Services\QuotationGenerate;

use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class QuotationGenerateAutoQtRunner
{
    /**
     * @param  array{month?:string,test?:bool,dry_run?:bool,limit?:int|null,email?:string|null,customer?:string|null,batch?:int|null,item_pause?:int|null,chunk_pause?:int|null}  $options
     * @param  callable|null  $output
     * @return array{success:bool,processed:int,built:int,rendered:int,emailed:int,success_count:int,failed_count:int,test:bool,dry_run:bool,month:string,logs:array<int,string>}
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
        $emailTo = trim((string) ($options['email'] ?? ''));
        if ($emailTo !== '') {
            $test = true;
        }
        if ($test && $limit === null) {
            $limit = 2;
        }
        $limitCapped = false;
        if ($test && $limit !== null && $limit > 20) {
            $limit = 20;
            $limitCapped = true;
        }

        QuotationGenerateCampaign::apply('auto_qt');
        if ($test) {
            if ($emailTo === '') {
                $emailTo = 'harold@intilab.com';
            }
            $this->assertTestEmails($emailTo);
            config([
                'quotation_auto.generate.send_email' => true,
                'quotation_auto.generate.email_test_mode' => true,
                'quotation_auto.generate.email_test_to' => $emailTo,
            ]);
        }

        $logs = [];
        $write = function ($line, $level = 'line') use ($output, &$logs) {
            $logs[] = $line;
            $this->writeChannel($line, $level);
            if (is_callable($output)) {
                $output($line, $level);
            }
        };

        $this->ensurePromoPengujian($window, $write);

        $discovery = new QuotationGenerateDiscoveryService();
        $processor = new QuotationGenerateProcessingService();

        $batchSize = max(1, (int) ($options['batch'] ?? config('quotation_auto.generate.dispatch_chunk_size', 100)));
        $emailPauseSeconds = max(0, (int) ($options['item_pause'] ?? config('quotation_auto.generate.email_item_pause_seconds', 1)));
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
            $testEmails = $this->splitEmails((string) config('quotation_auto.generate.email_test_to'));
            $testSales = (string) config('quotation_auto.generate.email_test_sales', 'harold@intilab.com');
            $write(
                'Email test mode    : ON → To ' . ($testEmails[0] ?? '-')
                . ' | BCC sales@intilab.com + ' . $testSales . ' (anggap sales PJ) + atasan SUPERVISOR-nya (manager tidak ikut)'
            );
        } elseif (!$sendEmail) {
            $write('Email customer     : tidak dikirim (send_email=false)');
        } else {
            $write('Email customer     : ON ke email_pic_order');
            $write('BCC                : sales@intilab.com + sales PJ + atasan grade SUPERVISOR');
        }
        $write('Window order skip  : ' . $window->orderFrom()->toDateTimeString() . ' s/d ' . $window->orderUntil()->copy()->subSecond()->toDateTimeString());
        $write('Syarat             : punya QT + tidak ada order di window 6 bulan');
        $write('Data pendukung     : QT terakhir wajib ada; kosong/{} langsung skip');
        $write('Sampling           : dihitung ulang pakai nominal terbaru');
        $write('Diskon QT lama     : dihapus semua');
        $write('Alur               : build semua → render semua → email semua (delay email ' . $emailPauseSeconds . ' detik)');
        if ($limitCapped) {
            $write('Limit tes dipotong ke 20 supaya tidak kebablasan.', 'warn');
        }

        $write('=== Mengumpulkan kandidat ===', 'info');
        $eligible = $discovery->fetchEligibleHasQuotationNoRecentOrder(
            $window->orderFrom(),
            $window->orderUntil(),
            $limit
        );
        if ($customerFilter !== null) {
            $eligible = $eligible->where('customer_id', $customerFilter)->values();
            $write('Filter customer    : ' . $customerFilter);
        }
        $write('Kandidat terkumpul : ' . $eligible->count());

        if ($eligible->isEmpty()) {
            $write('Tidak ada kandidat ELIGIBLE untuk diproses.', 'warn');

            return $this->summaryPayload($window, $test, $dryRun, $logs, 0, 0, 0, 0, 0);
        }

        $created = [];
        $rendered = [];
        $createFailed = 0;
        $createSkipped = 0;
        $renderFailed = 0;
        $emailFailed = 0;
        $emailSuccess = 0;
        $emailSkipped = 0;
        $buildIndex = 0;
        $renderIndex = 0;
        $emailIndex = 0;
        $buildTotal = $eligible->count();

        $write('=== Fase 1/3 BUILD (' . $buildTotal . ' QT) ===', 'info');
        $this->eachPaused($eligible, $batchSize, 0, 0, $dryRun, function ($row) use ($processor, $dryRun, $write, &$created, &$createFailed, &$createSkipped, &$buildIndex, $buildTotal) {
            $buildIndex++;
            $prefix = '[BUILD ' . $buildIndex . '/' . $buildTotal . '] ' . $row->customer_id;
            try {
                $result = $processor->createFromCandidate($row, $dryRun);
                $created[] = [
                    'customer_id' => $row->customer_id,
                    'source_quotation_id' => (int) $result['source_quotation_id'],
                    'new_quotation_id' => $result['new_quotation_id'] ?? null,
                    'new_no_document' => $result['new_no_document'] ?? null,
                ];
                $write($prefix . ' OK | ' . $result['message']);
            } catch (QuotationGenerateSkipException $e) {
                $createSkipped++;
                $write($prefix . ' SKIP | ' . $e->getMessage(), 'warn');
            } catch (\Throwable $e) {
                $createFailed++;
                $write($prefix . ' ERROR | ' . $e->getMessage(), 'error');
            }
        });
        $write(
            'Build selesai      : ' . count($created) . ' OK, ' . $createSkipped . ' skip, ' . $createFailed . ' error',
            'info'
        );

        $renderTotal = count($created);
        $write('=== Fase 2/3 RENDER (' . $renderTotal . ' QT) ===', 'info');
        $this->eachPaused($created, $batchSize, 0, 0, $dryRun, function ($item) use ($processor, $dryRun, $write, &$rendered, &$renderFailed, &$renderIndex, $renderTotal) {
            $renderIndex++;
            $label = $item['new_no_document'] ?: $item['customer_id'];
            $prefix = '[RENDER ' . $renderIndex . '/' . $renderTotal . '] ' . $label;
            try {
                if ($dryRun) {
                    $rendered[] = $item;
                    $write($prefix . ' OK | DRY RUN: render PDF + link dari source ' . $item['source_quotation_id']);
                    return;
                }

                if (empty($item['new_quotation_id'])) {
                    throw new \RuntimeException('QT baru belum punya id, render dilewati');
                }
                $quotation = QuotationNonKontrak::findOrFail((int) $item['new_quotation_id']);
                $result = $processor->renderQuotation($quotation, false, (int) $item['source_quotation_id']);
                $rendered[] = $item;
                $write($prefix . ' OK | ' . $result['message']);
            } catch (\Throwable $e) {
                $renderFailed++;
                $write($prefix . ' ERROR | ' . $e->getMessage(), 'error');
            }
        });
        $write('Render selesai     : ' . count($rendered) . ' OK, ' . $renderFailed . ' error', 'info');

        $emailTotal = count($rendered);
        $write('=== Fase 3/3 EMAIL (' . $emailTotal . ' QT, delay ' . $emailPauseSeconds . ' detik) ===', 'info');
        $this->eachPaused($rendered, $batchSize, $emailPauseSeconds, $chunkPauseSeconds, $dryRun, function ($item) use ($processor, $dryRun, $write, &$emailSuccess, &$emailFailed, &$emailSkipped, &$emailIndex, $emailTotal) {
            $emailIndex++;
            $label = $item['new_no_document'] ?: $item['customer_id'];
            $prefix = '[EMAIL ' . $emailIndex . '/' . $emailTotal . '] ' . $label;
            try {
                if ($dryRun) {
                    $emailSuccess++;
                    $write($prefix . ' OK | DRY RUN: email setelah semua PDF siap');
                    return;
                }

                if (empty($item['new_quotation_id'])) {
                    throw new \RuntimeException('QT baru belum punya id, email dilewati');
                }
                $quotation = QuotationNonKontrak::findOrFail((int) $item['new_quotation_id']);
                $result = $processor->emailQuotation($quotation, false, (int) $item['source_quotation_id']);
                if (!empty($result['skipped'])) {
                    $emailSkipped++;
                    $write($prefix . ' SKIP | ' . $result['message'], 'warn');
                    return;
                }
                $emailSuccess++;
                $write($prefix . ' OK | ' . $result['message']);
            } catch (\Throwable $e) {
                $emailFailed++;
                $write($prefix . ' ERROR | ' . $e->getMessage(), 'error');
            }
        });
        $write(
            'Email selesai      : ' . $emailSuccess . ' terkirim, ' . $emailSkipped . ' dilewati, ' . $emailFailed . ' error',
            'info'
        );

        $failed = $createFailed + $renderFailed + $emailFailed;
        $write('=== Ringkasan ===', 'info');
        $write('Kandidat : ' . $eligible->count());
        $write('Built    : ' . count($created));
        $write('Rendered : ' . count($rendered));
        $write('Emailed  : ' . $emailSuccess . ($emailSkipped > 0 ? ' (dilewati ' . $emailSkipped . ')' : ''));
        $write(
            'Error    : ' . $failed
            . ' (build ' . $createFailed . ' / render ' . $renderFailed . ' / email ' . $emailFailed . ')'
            . ($failed > 0 ? ' — proses tetap dilanjutkan' : ''),
            $failed > 0 ? 'warn' : 'info'
        );

        return $this->summaryPayload(
            $window,
            $test,
            $dryRun,
            $logs,
            $eligible->count(),
            count($created),
            count($rendered),
            $emailSuccess,
            $failed
        );
    }

    /**
     * Error per item tidak menggagalkan job — success tetap true.
     *
     * @return array{success:bool,processed:int,built:int,rendered:int,emailed:int,success_count:int,failed_count:int,test:bool,dry_run:bool,month:string,logs:array<int,string>}
     */
    private function summaryPayload(
        QuotationAutoQtMonth $window,
        bool $test,
        bool $dryRun,
        array $logs,
        int $processed,
        int $built,
        int $rendered,
        int $emailed,
        int $failed
    ): array {
        return [
            'success' => true,
            'processed' => $processed,
            'built' => $built,
            'rendered' => $rendered,
            'emailed' => $emailed,
            'success_count' => $emailed,
            'failed_count' => $failed,
            'test' => $test,
            'dry_run' => $dryRun,
            'month' => $window->token(),
            'logs' => $logs,
        ];
    }

    private function writeChannel(string $line, string $level): void
    {
        try {
            $dir = storage_path('logs/auto_qt');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $channel = Log::channel('auto_qt');
            if ($level === 'error') {
                $channel->error($line);
            } elseif ($level === 'warn') {
                $channel->warning($line);
            } else {
                $channel->info($line);
            }
        } catch (\Throwable $exception) {
            try {
                Log::info('[auto-qt] ' . $line);
            } catch (\Throwable $ignored) {
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitEmails(string $raw): array
    {
        $emails = [];
        foreach (preg_split('/[,;]+/', $raw) as $item) {
            $email = strtolower(trim($item));
            if ($email === '') {
                continue;
            }
            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    private function assertTestEmails(string $raw): void
    {
        $emails = $this->splitEmails($raw);
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Email test tidak valid: ' . $email);
            }
        }
        if ($emails === []) {
            throw new \InvalidArgumentException('Email test wajib. Contoh: --email=harold@intilab.com,atasan@intilab.com');
        }
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
