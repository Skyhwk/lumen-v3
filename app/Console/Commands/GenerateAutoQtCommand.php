<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesAutoQtJob;
use App\Services\QuotationGenerate\QuotationAutoQtJobState;
use App\Services\QuotationGenerate\QuotationAutoQtMonth;
use App\Services\QuotationGenerate\QuotationGenerateAutoQtRunner;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class GenerateAutoQtCommand extends Command
{
    use DispatchesAutoQtJob;

    protected $signature = 'generateAutoQt
                            {--month= : november atau 11-2026}
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
                            {--all : Generate + render PDF + email sekaligus}
                            {--limit= : Max eligible rows to process}
                            {--batch= : Chunk size (default 100)}
                            {--item-pause= : Jeda antar email detik (default 1, hanya --all)}
                            {--chunk-pause= : Jeda antar chunk detik (default 0, hanya --all)}
                            {--dry-run : Hanya simulasi, tanpa copy}
                            {--test : Mode tes: limit kecil}
                            {--email= : Email test (wajib kalau --all --test). Contoh: harold@intilab.com}
                            {--tanggal= : Tanggal penawaran Y-m-d. Kosong = hari ini}
                            {--direct : Langsung PHP, skip Python}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto QT: default copy data saja. --all = generate + render + email. Pisah: renderAutoQt / emailAutoQt --promo=ID';

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

        $window = QuotationAutoQtMonth::parse($month);
        $all = $options['phase'] === QuotationAutoQtJobState::PHASE_ALL;
        $this->info('Preflight generateAutoQt (' . ($all ? 'BUILD + RENDER + EMAIL' : 'BUILD DATA') . ')');
        $this->line('Bulan     : ' . $window->label() . ' (' . $window->token() . ')');
        $this->line('Promo     : ' . $window->promoCode());
        $this->line('Mode      : ' . ($options['dry_run'] ? 'DRY RUN' : ($options['test'] ? 'TEST' : 'PRODUKSI')));
        $this->line('Limit     : ' . ($options['limit'] !== null ? $options['limit'] : ($options['test'] ? '2 (default tes)' : 'semua eligible')));
        $this->line('Tgl QT    : ' . ($options['tanggal_penawaran'] ?: ('hari ini (' . date('Y-m-d') . ')')));
        if ($all && $options['test']) {
            $this->line('Email     : ' . ($options['email'] ?: 'harold@intilab.com') . ' (bukan customer)');
        } elseif ($all) {
            $this->warn('Email     : produksi — ke PIC order + BCC sales/supervisor');
        }
        $this->line('Python    : ' . (!$this->option('direct') && empty($options['dry_run']) && empty($options['customer']) ? 'YA' : 'tidak'));
        if (!$all) {
            $this->line('Berikutnya: renderAutoQt --promo=<id> lalu emailAutoQt --promo=<id>');
            $this->line('Sekaligus : php artisan generateAutoQt --' . $window->token() . ' --all');
        }

        return $this->dispatchAutoQt($options);
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

        throw new \InvalidArgumentException('Bulan target wajib. Contoh: php artisan generateAutoQt --november atau --11-2026');
    }

    /**
     * @return array{phase:string,month:string,test:bool,dry_run:bool,limit:?int,customer:?string,batch:?int}
     */
    private function resolveOptions(string $month): array
    {
        QuotationAutoQtMonth::parse($month);

        $email = trim((string) ($this->option('email') ?? ''));
        $all = (bool) $this->option('all');
        $test = (bool) $this->option('test') || ($all && $email !== '');
        if ($all && $test && $email === '') {
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

        return [
            'phase' => $all ? QuotationAutoQtJobState::PHASE_ALL : QuotationAutoQtJobState::PHASE_BUILD,
            'month' => $month,
            'test' => $test,
            'email' => $email !== '' ? $email : null,
            'dry_run' => (bool) $this->option('dry-run'),
            'limit' => $this->parsePositiveInt($this->option('limit'), '--limit'),
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
            'tanggal_penawaran' => QuotationGenerateAutoQtRunner::normalizeTanggalPenawaran($this->option('tanggal')),
        ];
    }
}
