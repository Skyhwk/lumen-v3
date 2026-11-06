<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationAutoQtJobState;
use App\Services\QuotationGenerate\QuotationAutoQtMonth;
use App\Services\QuotationGenerate\QuotationGenerateAutoQtRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Input\InputOption;

class GenerateAutoQtCommand extends Command
{
    protected $signature = 'generateAutoQt
                            {--month= : desember atau 12-2026}
                            {--januari}
                            {--februari}
                            {--maret}
                            {--april}
                            {--mei}
                            {--juni}
                            {--juli}
                            {--agustus}
                            {--september}
                            {--oktober}
                            {--november}
                            {--desember}
                            {--limit= : Max eligible rows to process}
                            {--batch= : Chunk size (default 100)}
                            {--item-pause= : Jeda antar email detik (default 1)}
                            {--chunk-pause= : Jeda antar chunk detik (default 0)}
                            {--dry-run : Hanya simulasi, tanpa copy/generate/email}
                            {--test : Mode tes: email tidak ke customer}
                            {--email= : Email test, bisa koma. Contoh: harold@intilab.com,atasan@intilab.com}
                            {--direct : Langsung PHP, skip Python}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto quotation umum: --desember / --12-2026. PT punya QT + tidak ada order 6 bulan ke belakang';

    public function __construct()
    {
        parent::__construct();
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (is_string($arg) && preg_match('/^--(\d{1,2}-\d{4})$/', $arg, $match)) {
                if (!$this->getDefinition()->hasOption($match[1])) {
                    $this->addOption($match[1], null, InputOption::VALUE_NONE, 'Target bulan MM-YYYY');
                }
            }
        }
    }

    public function handle(): int
    {
        try {
            $month = $this->resolveMonth();
            $options = $this->resolveOptions($month);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->printPreflight($month, $options);

        $needsLocalOptions = !empty($options['dry_run'])
            || ($options['customer'] !== null && $options['customer'] !== '');
        $usePython = !$this->option('direct') && !$needsLocalOptions;

        if ($usePython) {
            return $this->dispatchViaPython($month, $options);
        }

        if ($needsLocalOptions && !$this->option('direct')) {
            $this->line('Opsi dry-run / customer memakai PHP langsung.');
        }

        return $this->runDirect($month, $options);
    }

    private function resolveMonth(): string
    {
        $fromArgv = QuotationAutoQtMonth::extractFromArgv($_SERVER['argv'] ?? []);
        if ($fromArgv !== null) {
            return $fromArgv;
        }

        $monthOption = $this->option('month');
        if ($monthOption !== null && $monthOption !== '') {
            return (string) $monthOption;
        }

        throw new \InvalidArgumentException('Bulan target wajib. Contoh: php artisan generateAutoQt --desember atau --12-2026');
    }

    /**
     * @return array{test:bool,dry_run:bool,email:?string,limit:?int,customer:?string,batch:?int,item_pause:?int,chunk_pause:?int}
     */
    private function resolveOptions(string $month): array
    {
        $email = trim((string) ($this->option('email') ?? ''));
        $test = (bool) $this->option('test') || $email !== '';
        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? (int) $this->option('limit')
            : null;
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('--limit harus angka positif.');
        }
        if ($test && $email === '') {
            $email = 'harold@intilab.com';
        }
        if ($email !== '') {
            foreach (preg_split('/[,;]+/', $email) as $item) {
                $item = strtolower(trim($item));
                if ($item === '') {
                    continue;
                }
                if (!filter_var($item, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Email test tidak valid: ' . $item);
                }
            }
        }

        QuotationAutoQtMonth::parse($month);

        return [
            'test' => $test,
            'dry_run' => (bool) $this->option('dry-run'),
            'email' => $email !== '' ? $email : null,
            'limit' => $limit,
            'customer' => $this->option('customer') !== null && $this->option('customer') !== ''
                ? (string) $this->option('customer')
                : null,
            'batch' => $this->option('batch') !== null ? (int) $this->option('batch') : null,
            'item_pause' => $this->option('item-pause') !== null && $this->option('item-pause') !== ''
                ? (int) $this->option('item-pause')
                : null,
            'chunk_pause' => $this->option('chunk-pause') !== null && $this->option('chunk-pause') !== ''
                ? (int) $this->option('chunk-pause')
                : null,
        ];
    }

    /**
     * @param  array{test:bool,dry_run:bool,email:?string,limit:?int,customer:?string}  $options
     */
    private function printPreflight(string $month, array $options): void
    {
        $window = QuotationAutoQtMonth::parse($month);
        $this->info('Preflight generateAutoQt');
        $this->line('Bulan     : ' . $window->label() . ' (' . $window->token() . ')');
        $this->line('Mode      : ' . ($options['dry_run'] ? 'DRY RUN' : ($options['test'] ? 'TEST' : 'PRODUKSI')));
        $this->line('Limit     : ' . ($options['limit'] !== null ? $options['limit'] : ($options['test'] ? '2 (default tes)' : 'semua eligible')));
        if ($options['test']) {
            $this->line('Email     : ' . ($options['email'] ?: 'harold@intilab.com') . ' (bukan customer)');
        } else {
            $this->warn('Email     : produksi — send_email default OFF, tidak ke PIC kecuali env nyala');
        }
        $this->line('Python    : ' . (!$this->option('direct') && empty($options['dry_run']) && empty($options['customer']) ? 'YA' : 'tidak'));
    }

    private function dispatchViaPython(string $month, array $options): int
    {
        $test = !empty($options['test']);
        QuotationAutoQtJobState::write($month, $test, [
            'email' => $options['email'] ?? null,
            'limit' => $options['limit'] ?? null,
        ]);

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
        $this->line('Bulan    : ' . QuotationAutoQtMonth::parse($month)->label());
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
            $this->line('Coba lokal: php artisan generateAutoQt --' . $month . ' --direct --test --email=' . ($options['email'] ?: 'harold@intilab.com'));

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
            'month' => $lumenBody['data']['month'] ?? $month,
            'processed' => $lumenBody['data']['processed'] ?? null,
            'built' => $lumenBody['data']['built'] ?? null,
            'rendered' => $lumenBody['data']['rendered'] ?? null,
            'emailed' => $lumenBody['data']['emailed'] ?? null,
            'failed_count' => $lumenBody['data']['failed_count'] ?? null,
        ], JSON_UNESCAPED_UNICODE));

        $ok = ($body['status'] ?? '') === 'success' && !empty($lumenBody['data']['success']);

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function runDirect(string $month, array $options): int
    {
        try {
            $result = (new QuotationGenerateAutoQtRunner())->run(array_merge($options, [
                'month' => $month,
            ]), function ($line, $level) {
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
}
