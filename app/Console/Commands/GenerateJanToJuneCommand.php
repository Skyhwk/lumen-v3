<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationGenerateJanToJuneRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GenerateJanToJuneCommand extends Command
{
    protected $signature = 'generateJanToJune
                            {--limit= : Max eligible rows to process}
                            {--batch= : Chunk size (default 100)}
                            {--item-pause= : Jeda antar setiap QT detik (default 5)}
                            {--chunk-pause= : Jeda antar chunk detik (default 0)}
                            {--dry-run : Hanya simulasi, tanpa copy/generate/email}
                            {--test : Tes 2 QT, email hanya ke harold@intilab.com (tanpa BCC)}
                            {--direct : Langsung PHP, skip Python}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto quotation Jan–June via Python invoke (nunggu selesai), fallback --direct ke PHP';

    public function handle(): int
    {
        $needsLocalOptions = $this->option('dry-run')
            || ($this->option('customer') !== null && $this->option('customer') !== '')
            || ($this->option('limit') !== null && !$this->option('test'));
        $usePython = !$this->option('direct') && !$needsLocalOptions;

        if ($usePython) {
            return $this->dispatchViaPython((bool) $this->option('test'));
        }

        if ($needsLocalOptions && !$this->option('direct')) {
            $this->line('Opsi dry-run / customer / limit memakai PHP langsung.');
        }

        return $this->runDirect();
    }

    private function dispatchViaPython(bool $test): int
    {
        $url = (string) config('quotation_auto.python.invoke_url');
        $timeout = (int) config('quotation_auto.python.invoke_timeout_seconds', 1800);
        $lumenTimeout = (int) config('quotation_auto.python.lumen_timeout_seconds', 1700);
        $payload = [
            'controller' => (string) config('quotation_auto.python.controller'),
            'function' => $test
                ? (string) config('quotation_auto.python.test_function')
                : (string) config('quotation_auto.python.run_function'),
            'async' => false,
            'timeout' => $lumenTimeout,
        ];

        $this->info('Kirim ke Python /lumen/invoke, menunggu Lumen selesai...');
        $this->line('URL      : ' . $url);
        $this->line('Slice    : ' . $payload['controller'] . '@' . $payload['function']);
        $this->line('Timeout  : PHP wait ' . $timeout . 's / Python→Lumen ' . $lumenTimeout . 's');

        $http = Http::timeout($timeout)->acceptJson()->asJson();
        $secret = trim((string) config('quotation_auto.python.invoke_secret'));
        if ($secret !== '') {
            $http = $http->withHeaders(['X-Worker-Secret' => $secret]);
        }

        try {
            $response = $http->post($url, $payload);
        } catch (\Throwable $exception) {
            $this->error('Gagal memanggil Python: ' . $exception->getMessage());
            $this->line('Coba lokal: php artisan generateJanToJune --direct' . ($test ? ' --test' : ''));

            return self::FAILURE;
        }

        $body = $response->json() ?: ['raw' => $response->body()];
        if (!$response->successful()) {
            $this->error('Python/Lumen error HTTP ' . $response->status());
            $this->line(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        $this->info('Python invoke selesai (HTTP ' . $response->status() . ').');
        $lumenBody = $body['lumen_body'] ?? $body;
        $logs = $lumenBody['data']['logs'] ?? [];
        foreach ($logs as $line) {
            $this->line((string) $line);
        }
        $this->line(json_encode([
            'processed' => $lumenBody['data']['processed'] ?? null,
            'success_count' => $lumenBody['data']['success_count'] ?? null,
            'failed_count' => $lumenBody['data']['failed_count'] ?? null,
        ], JSON_UNESCAPED_UNICODE));

        $ok = ($body['status'] ?? '') === 'success' && !empty($lumenBody['data']['success']);

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function runDirect(): int
    {
        $result = (new QuotationGenerateJanToJuneRunner())->run([
            'test' => (bool) $this->option('test'),
            'dry_run' => (bool) $this->option('dry-run'),
            'limit' => $this->option('limit') !== null ? (int) $this->option('limit') : null,
            'customer' => $this->option('customer'),
            'batch' => $this->option('batch') !== null ? (int) $this->option('batch') : null,
            'item_pause' => $this->option('item-pause') !== null && $this->option('item-pause') !== ''
                ? (int) $this->option('item-pause')
                : null,
            'chunk_pause' => $this->option('chunk-pause') !== null && $this->option('chunk-pause') !== ''
                ? (int) $this->option('chunk-pause')
                : null,
        ], function ($line, $level) {
            if ($level === 'error') {
                $this->error($line);
            } elseif ($level === 'warn') {
                $this->warn($line);
            } elseif ($level === 'info') {
                $this->info($line);
            } else {
                $this->line($line);
            }
        });

        return !empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }
}
