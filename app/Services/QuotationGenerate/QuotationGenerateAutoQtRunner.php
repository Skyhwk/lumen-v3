<?php

namespace App\Services\QuotationGenerate;

use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class QuotationGenerateAutoQtRunner
{
    /**
     * @param  array{phase?:string,month?:string,promo_id?:int|null,test?:bool,dry_run?:bool,limit?:int|null,email?:string|null,customer?:string|null,batch?:int|null,item_pause?:int|null,chunk_pause?:int|null}  $options
     * @param  callable|null  $output
     * @return array{success:bool,phase:string,processed:int,built:int,rendered:int,emailed:int,success_count:int,failed_count:int,test:bool,dry_run:bool,month:?string,promo_id:?int,logs:array<int,string>}
     */
    public function run(array $options = [], $output = null): array
    {
        $phase = (string) ($options['phase'] ?? QuotationAutoQtJobState::PHASE_BUILD);
        if (!in_array($phase, [
            QuotationAutoQtJobState::PHASE_BUILD,
            QuotationAutoQtJobState::PHASE_RENDER,
            QuotationAutoQtJobState::PHASE_EMAIL,
            QuotationAutoQtJobState::PHASE_ALL,
        ], true)) {
            throw new \InvalidArgumentException('Phase tidak dikenali: ' . $phase);
        }

        QuotationGenerateCampaign::apply('auto_qt');
        $this->applyTanggalPenawaran($options);

        $logs = [];
        $write = function ($line, $level = 'line') use ($output, &$logs) {
            $logs[] = $line;
            $this->writeChannel($line, $level);
            if (is_callable($output)) {
                $output($line, $level);
            }
        };

        if ($phase === QuotationAutoQtJobState::PHASE_ALL) {
            return $this->runAll($options, $write, $logs);
        }

        if ($phase === QuotationAutoQtJobState::PHASE_BUILD) {
            return $this->runBuild($options, $write, $logs);
        }

        return $this->runByPromo($phase, $options, $write, $logs);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  callable  $write
     * @param  array<int, string>  $logs
     */
    private function runAll(array $options, $write, array &$logs): array
    {
        $write('Alur               : BUILD → RENDER → EMAIL (--all)', 'info');
        $build = $this->runBuild($options, $write, $logs);
        $promoId = (int) ($build['promo_id'] ?? 0);
        if ($promoId < 1) {
            throw new \RuntimeException('promo_id tidak terbentuk setelah generate data.');
        }

        $followUp = $options;
        $followUp['promo_id'] = $promoId;
        unset($followUp['limit']);

        $render = $this->runByPromo(QuotationAutoQtJobState::PHASE_RENDER, $followUp, $write, $logs);
        $email = $this->runByPromo(QuotationAutoQtJobState::PHASE_EMAIL, $followUp, $write, $logs);
        $failed = (int) $build['failed_count'] + (int) $render['failed_count'] + (int) $email['failed_count'];

        $write('=== Ringkasan --all ===', 'info');
        $write('Built    : ' . $build['built']);
        $write('Rendered : ' . $render['rendered']);
        $write('Emailed  : ' . $email['emailed']);
        $write('Error    : ' . $failed, $failed > 0 ? 'warn' : 'info');

        return $this->summaryPayload(
            QuotationAutoQtJobState::PHASE_ALL,
            !empty($options['test']) || trim((string) ($options['email'] ?? '')) !== '',
            !empty($options['dry_run']),
            $logs,
            $build['month'] ?? null,
            $promoId,
            (int) $build['processed'],
            (int) $build['built'],
            (int) $render['rendered'],
            (int) $email['emailed'],
            $failed
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  callable  $write
     * @param  array<int, string>  $logs
     */
    private function runBuild(array $options, $write, array &$logs): array
    {
        $monthInput = (string) ($options['month'] ?? '');
        if ($monthInput === '') {
            throw new \InvalidArgumentException('Bulan target wajib diisi. Contoh: --desember atau --11-2026');
        }

        $window = QuotationAutoQtMonth::parse($monthInput);
        $test = !empty($options['test']);
        $dryRun = !empty($options['dry_run']);
        $limit = $this->resolveLimit($options, $test, $write);

        $promo = $this->ensurePromoPengujian($window, $write);
        $promoId = (int) $promo->id;

        $discovery = new QuotationGenerateDiscoveryService();
        $processor = new QuotationGenerateProcessingService();
        $batchSize = max(1, (int) ($options['batch'] ?? config('quotation_auto.generate.dispatch_chunk_size', 100)));
        $customerFilter = isset($options['customer']) && $options['customer'] !== '' ? (string) $options['customer'] : null;
        $fallbackSalesId = (int) config('quotation_auto.generate.fallback_sales_id', 1068);
        $marker = (string) config('quotation_auto.generate.kode_promo_marker');

        $write('Generate Auto QT — BUILD DATA ' . $window->label() . ($dryRun ? ' (DRY RUN)' : '') . ($test ? ' (TEST)' : ''), 'info');
        $write('Kode promo         : ' . $marker . ' (promo_id ' . $promoId . ')');
        $write('Sales              : master_pelanggan.sales_id, fallback id ' . $fallbackSalesId);
        $write('QT sumber          : dari Juni ' . $window->sourceJuneEnd()->year . ' ke belakang (s/d ' . $window->sourceJuneEnd()->toDateString() . ')');
        $write('Syarat             : punya QT di window itu + data pendukung sampling. Order tidak dicek.');
        $write('Data pendukung     : QT terakhir di window wajib ada; kosong/{} langsung skip');
        $write('Sampling           : dihitung ulang pakai nominal terbaru');
        $write('Diskon QT lama     : dihapus semua');
        $tanggalPenawaran = trim((string) config('quotation_auto.generate.tanggal_penawaran', ''));
        $write('Tanggal penawaran  : ' . ($tanggalPenawaran !== '' ? $tanggalPenawaran : ('hari ini (' . Carbon::now()->toDateString() . ')')));
        $write('Alur               : copy data saja. Render: php artisan renderAutoQt --promo=' . $promoId);
        $write('                   Email : php artisan emailAutoQt --promo=' . $promoId);
        $write('                   Semua : php artisan generateAutoQt --' . $window->token() . ' --all');

        $write('=== Mengumpulkan kandidat ===', 'info');
        $eligible = $discovery->fetchEligibleFromJuneBackwards(
            $window->sourceUntil(),
            $limit
        );
        if ($customerFilter !== null) {
            $eligible = $eligible->where('customer_id', $customerFilter)->values();
            $write('Filter customer    : ' . $customerFilter);
        }
        $write('Kandidat terkumpul : ' . $eligible->count());

        if ($eligible->isEmpty()) {
            $write('Tidak ada kandidat ELIGIBLE untuk diproses.', 'warn');

            return $this->summaryPayload(
                QuotationAutoQtJobState::PHASE_BUILD,
                $test,
                $dryRun,
                $logs,
                $window->token(),
                $promoId,
                0,
                0,
                0,
                0,
                0
            );
        }

        $created = [];
        $createFailed = 0;
        $createSkipped = 0;
        $buildIndex = 0;
        $buildTotal = $eligible->count();

        $write('=== BUILD (' . $buildTotal . ' QT) ===', 'info');
        $this->eachPaused($eligible, $batchSize, 0, 0, $dryRun, function ($row) use ($processor, $dryRun, $write, &$created, &$createFailed, &$createSkipped, &$buildIndex, $buildTotal) {
            $buildIndex++;
            $prefix = '[BUILD ' . $buildIndex . '/' . $buildTotal . '] ' . $row->customer_id;
            try {
                $result = $processor->createFromCandidate($row, $dryRun);
                $created[] = $result;
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
        $write('Lanjut render PDF  : php artisan renderAutoQt --promo=' . $promoId, 'info');

        return $this->summaryPayload(
            QuotationAutoQtJobState::PHASE_BUILD,
            $test,
            $dryRun,
            $logs,
            $window->token(),
            $promoId,
            $eligible->count(),
            count($created),
            0,
            0,
            $createFailed
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  callable  $write
     * @param  array<int, string>  $logs
     */
    private function runByPromo(string $phase, array $options, $write, array &$logs): array
    {
        $promoId = (int) ($options['promo_id'] ?? 0);
        if ($promoId < 1) {
            throw new \InvalidArgumentException('promo_id wajib. Contoh: --promo=12');
        }

        $test = !empty($options['test']);
        $dryRun = !empty($options['dry_run']);
        $emailTo = trim((string) ($options['email'] ?? ''));
        if ($emailTo !== '') {
            $test = true;
        }

        $promo = $this->loadPromoPengujian($promoId);
        config(['quotation_auto.generate.kode_promo_marker' => (string) $promo->kode_promo]);

        if ($phase === QuotationAutoQtJobState::PHASE_EMAIL && !$dryRun) {
            config(['quotation_auto.generate.send_email' => true]);
            if ($test) {
                if ($emailTo === '') {
                    $emailTo = 'harold@intilab.com';
                }
                $this->assertTestEmails($emailTo);
                config([
                    'quotation_auto.generate.email_test_mode' => true,
                    'quotation_auto.generate.email_test_to' => $emailTo,
                ]);
            }
        }

        $limit = array_key_exists('limit', $options) && $options['limit'] !== null ? (int) $options['limit'] : null;
        $batchSize = max(1, (int) ($options['batch'] ?? config('quotation_auto.generate.dispatch_chunk_size', 100)));
        $emailPauseSeconds = max(0, (int) ($options['item_pause'] ?? config('quotation_auto.generate.email_item_pause_seconds', 1)));
        $chunkPauseSeconds = max(0, (int) ($options['chunk_pause'] ?? config('quotation_auto.generate.dispatch_chunk_pause_seconds', 0)));
        $customerFilter = isset($options['customer']) && $options['customer'] !== '' ? (string) $options['customer'] : null;

        $quotations = $this->fetchQuotationsByPromo($promo, $phase, $limit, $customerFilter);
        $label = $phase === QuotationAutoQtJobState::PHASE_RENDER ? 'RENDER PDF' : 'EMAIL';
        $write(
            'Auto QT — ' . $label . ' promo_id ' . $promoId . ' (' . $promo->kode_promo . ')'
            . ($dryRun ? ' (DRY RUN)' : '')
            . ($test ? ' (TEST)' : ''),
            'info'
        );
        $write('Kode promo         : ' . $promo->kode_promo);
        if ($phase === QuotationAutoQtJobState::PHASE_EMAIL) {
            $sendEmail = (bool) config('quotation_auto.generate.send_email');
            $emailTestMode = (bool) config('quotation_auto.generate.email_test_mode');
            $write('Send email         : ' . ($sendEmail ? 'ON' : 'OFF'));
            if ($sendEmail && $emailTestMode) {
                $testEmails = $this->splitEmails((string) config('quotation_auto.generate.email_test_to'));
                $testSales = (string) config('quotation_auto.generate.email_test_sales', 'harold@intilab.com');
                $write(
                    'Email test mode    : ON → To ' . ($testEmails[0] ?? '-')
                    . ' | BCC sales@intilab.com + ' . $testSales . ' (anggap sales PJ) + atasan SUPERVISOR-nya'
                );
            } elseif ($sendEmail) {
                $write('Email customer     : ON ke email_pic_order');
                $write('BCC                : sales@intilab.com + sales PJ + atasan grade SUPERVISOR');
            }
            $write('Delay email        : ' . $emailPauseSeconds . ' detik');
        }
        $write('Antrian            : ' . $quotations->count());

        if ($quotations->isEmpty()) {
            $write('Tidak ada QT untuk ' . $label . ' pada promo_id ' . $promoId . '.', 'warn');

            return $this->summaryPayload($phase, $test, $dryRun, $logs, null, $promoId, 0, 0, 0, 0, 0);
        }

        $processor = new QuotationGenerateProcessingService();

        if ($phase === QuotationAutoQtJobState::PHASE_RENDER) {
            return $this->renderCollection(
                $quotations,
                $processor,
                $dryRun,
                $test,
                $batchSize,
                $write,
                $logs,
                $promoId
            );
        }

        return $this->emailCollection(
            $quotations,
            $processor,
            $dryRun,
            $test,
            $batchSize,
            $emailPauseSeconds,
            $chunkPauseSeconds,
            $write,
            $logs,
            $promoId
        );
    }

    private function renderCollection(
        Collection $quotations,
        QuotationGenerateProcessingService $processor,
        bool $dryRun,
        bool $test,
        int $batchSize,
        $write,
        array &$logs,
        int $promoId
    ): array {
        $ok = 0;
        $failed = 0;
        $index = 0;
        $total = $quotations->count();

        $write('=== RENDER (' . $total . ' QT) ===', 'info');
        $this->eachPaused($quotations, $batchSize, 0, 0, $dryRun, function ($quotation) use ($processor, $dryRun, $write, &$ok, &$failed, &$index, $total) {
            $index++;
            $prefix = '[RENDER ' . $index . '/' . $total . '] ' . $quotation->no_document;
            try {
                $result = $processor->renderQuotation($quotation, $dryRun);
                $ok++;
                $write($prefix . ' OK | ' . $result['message']);
            } catch (\Throwable $e) {
                $failed++;
                $write($prefix . ' ERROR | ' . $e->getMessage(), 'error');
            }
        });
        $write('Render selesai     : ' . $ok . ' OK, ' . $failed . ' error', 'info');
        $write('Lanjut email       : php artisan emailAutoQt --promo=' . $promoId, 'info');

        return $this->summaryPayload(
            QuotationAutoQtJobState::PHASE_RENDER,
            $test,
            $dryRun,
            $logs,
            null,
            $promoId,
            $total,
            0,
            $ok,
            0,
            $failed
        );
    }

    private function emailCollection(
        Collection $quotations,
        QuotationGenerateProcessingService $processor,
        bool $dryRun,
        bool $test,
        int $batchSize,
        int $emailPauseSeconds,
        int $chunkPauseSeconds,
        $write,
        array &$logs,
        int $promoId
    ): array {
        $ok = 0;
        $skipped = 0;
        $failed = 0;
        $index = 0;
        $total = $quotations->count();

        $write('=== EMAIL (' . $total . ' QT, delay ' . $emailPauseSeconds . ' detik) ===', 'info');
        $this->eachPaused($quotations, $batchSize, $emailPauseSeconds, $chunkPauseSeconds, $dryRun, function ($quotation) use ($processor, $dryRun, $write, &$ok, &$skipped, &$failed, &$index, $total) {
            $index++;
            $prefix = '[EMAIL ' . $index . '/' . $total . '] ' . $quotation->no_document;
            try {
                $result = $processor->emailQuotation($quotation, $dryRun);
                if (!empty($result['skipped'])) {
                    $skipped++;
                    $write($prefix . ' SKIP | ' . $result['message'], 'warn');
                    return;
                }
                $ok++;
                $write($prefix . ' OK | ' . $result['message']);
            } catch (\Throwable $e) {
                $failed++;
                $write($prefix . ' ERROR | ' . $e->getMessage(), 'error');
            }
        });
        $write('Email selesai      : ' . $ok . ' terkirim, ' . $skipped . ' dilewati, ' . $failed . ' error', 'info');

        return $this->summaryPayload(
            QuotationAutoQtJobState::PHASE_EMAIL,
            $test,
            $dryRun,
            $logs,
            null,
            $promoId,
            $total,
            0,
            0,
            $ok,
            $failed
        );
    }

    /**
     * @return Collection<int, QuotationNonKontrak>
     */
    private function fetchQuotationsByPromo(object $promo, string $phase, ?int $limit, ?string $customerId): Collection
    {
        if (!Schema::hasColumn('request_quotation', 'promo_id')) {
            throw new \RuntimeException('Kolom request_quotation.promo_id belum ada.');
        }

        $createdBy = (string) config('quotation_auto.generate.created_by', 'Quotation Auto Generate');
        $query = QuotationNonKontrak::query()
            ->where('is_active', 1)
            ->where('promo_id', (int) $promo->id)
            ->where('kode_promo', (string) $promo->kode_promo)
            ->where('created_by', $createdBy);

        if ($customerId !== null && $customerId !== '') {
            $query->where('pelanggan_ID', $customerId);
        }

        if ($phase === QuotationAutoQtJobState::PHASE_RENDER) {
            $query->where(function ($pending) {
                $pending->where('is_generated', '!=', 1)->orWhereNull('is_generated');
            });
        } elseif ($phase === QuotationAutoQtJobState::PHASE_EMAIL) {
            $query->where('is_generated', 1)
                ->where(function ($pending) {
                    $pending->where('is_emailed', '!=', 1)->orWhereNull('is_emailed');
                });
        }

        $query->orderBy('id');
        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function loadPromoPengujian(int $promoId): object
    {
        if (!Schema::hasTable('promo_pengujian')) {
            throw new \RuntimeException('Tabel promo_pengujian belum ada.');
        }

        $promo = DB::table('promo_pengujian')->where('id', $promoId)->first();
        if (!$promo) {
            throw new \InvalidArgumentException('Promo id ' . $promoId . ' tidak ditemukan di promo_pengujian.');
        }

        return $promo;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function applyTanggalPenawaran(array $options): void
    {
        $tanggal = self::normalizeTanggalPenawaran($options['tanggal_penawaran'] ?? null);
        if ($tanggal === null) {
            return;
        }

        config(['quotation_auto.generate.tanggal_penawaran' => $tanggal]);
    }

    public static function normalizeTanggalPenawaran($raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $match)) {
            $year = (int) $match[1];
            $month = (int) $match[2];
            $day = (int) $match[3];
        } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $match)) {
            $day = (int) $match[1];
            $month = (int) $match[2];
            $year = (int) $match[3];
        } else {
            throw new \InvalidArgumentException(
                'Format tanggal penawaran tidak dikenali: ' . $raw . '. Pakai --tanggal=2026-11-06 atau --tanggal=06-11-2026'
            );
        }

        if (!checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException('Tanggal penawaran tidak valid: ' . $raw);
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function resolveLimit(array $options, bool $test, $write): ?int
    {
        $limit = array_key_exists('limit', $options) && $options['limit'] !== null ? (int) $options['limit'] : null;
        if ($test && $limit === null) {
            $limit = 2;
        }
        if ($test && $limit !== null && $limit > 20) {
            $write('Limit tes dipotong ke 20 supaya tidak kebablasan.', 'warn');
            $limit = 20;
        }

        return $limit;
    }

    /**
     * @return array{success:bool,phase:string,processed:int,built:int,rendered:int,emailed:int,success_count:int,failed_count:int,test:bool,dry_run:bool,month:?string,promo_id:?int,logs:array<int,string>}
     */
    private function summaryPayload(
        string $phase,
        bool $test,
        bool $dryRun,
        array $logs,
        ?string $month,
        ?int $promoId,
        int $processed,
        int $built,
        int $rendered,
        int $emailed,
        int $failed
    ): array {
        $successCount = $phase === QuotationAutoQtJobState::PHASE_EMAIL
            ? $emailed
            : ($phase === QuotationAutoQtJobState::PHASE_RENDER ? $rendered : $built);

        return [
            'success' => true,
            'phase' => $phase,
            'processed' => $processed,
            'built' => $built,
            'rendered' => $rendered,
            'emailed' => $emailed,
            'success_count' => $successCount,
            'failed_count' => $failed,
            'test' => $test,
            'dry_run' => $dryRun,
            'month' => $month,
            'promo_id' => $promoId,
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

    private function ensurePromoPengujian(QuotationAutoQtMonth $window, $write): object
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

            return $existing;
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

        return DB::table('promo_pengujian')->where('id', $id)->first();
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
