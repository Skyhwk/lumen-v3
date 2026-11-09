<?php

namespace App\Console\Commands\Concerns;

use App\Services\QuotationGenerate\QuotationAutoQtJobState;
use App\Services\QuotationGenerate\QuotationGenerateAutoQtRunner;
use Illuminate\Support\Facades\Http;

trait DispatchesAutoQtJob
{
    /**
     * @param  array<string, mixed>  $options
     */
    protected function dispatchAutoQt(array $options): int
    {
        $needsLocal = !empty($options['dry_run'])
            || (isset($options['customer']) && $options['customer'] !== null && $options['customer'] !== '');
        $usePython = !$this->option('direct') && !$needsLocal;

        if ($usePython) {
            return $this->dispatchAutoQtViaPython($options);
        }

        if ($needsLocal && !$this->option('direct')) {
            $this->line('Opsi dry-run / customer memakai PHP langsung.');
        }

        return $this->runAutoQtDirect($options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function dispatchAutoQtViaPython(array $options): int
    {
        $test = !empty($options['test']);
        QuotationAutoQtJobState::write($options);

        $url = (string) config('quotation_auto.python_auto_qt.invoke_url', config('quotation_auto.python.invoke_url'));
        $timeout = (int) config('quotation_auto.python_auto_qt.invoke_timeout_seconds', config('quotation_auto.python.invoke_timeout_seconds', 1800));
        $lumenTimeout = (int) config('quotation_auto.python_auto_qt.lumen_timeout_seconds', config('quotation_auto.python.lumen_timeout_seconds', 1700));
        $payload = [
            'controller' => (string) config('quotation_auto.python_auto_qt.controller'),
            'function' => $test
                ? (string) config('quotation_auto.python_auto_qt.test_function')
                : (string) config('quotation_auto.python_auto_qt.run_function'),
            'async' => false,
            'timeout' => $lumenTimeout,
        ];

        $this->info('Kirim ke Python /lumen/invoke, menunggu Lumen selesai...');
        $this->line('Phase    : ' . ($options['phase'] ?? 'build'));
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
            $this->line('Coba lokal dengan --direct');

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
        foreach ($lumenBody['data']['logs'] ?? [] as $line) {
            $this->line((string) $line);
        }
        $this->line(json_encode([
            'phase' => $lumenBody['data']['phase'] ?? ($options['phase'] ?? null),
            'month' => $lumenBody['data']['month'] ?? ($options['month'] ?? null),
            'promo_id' => $lumenBody['data']['promo_id'] ?? ($options['promo_id'] ?? null),
            'processed' => $lumenBody['data']['processed'] ?? null,
            'built' => $lumenBody['data']['built'] ?? null,
            'rendered' => $lumenBody['data']['rendered'] ?? null,
            'emailed' => $lumenBody['data']['emailed'] ?? null,
            'failed_count' => $lumenBody['data']['failed_count'] ?? null,
        ], JSON_UNESCAPED_UNICODE));

        $ok = ($body['status'] ?? '') === 'success' && !empty($lumenBody['data']['success']);

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function runAutoQtDirect(array $options): int
    {
        try {
            $result = (new QuotationGenerateAutoQtRunner())->run($options, function ($line, $level) {
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
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return !empty($result['success']) ? self::SUCCESS : self::FAILURE;
    }

    protected function parsePositiveInt($value, string $flag): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;
        if ($int < 1) {
            throw new \InvalidArgumentException($flag . ' harus angka positif.');
        }

        return $int;
    }
}
