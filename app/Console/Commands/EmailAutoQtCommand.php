<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesAutoQtJob;
use App\Services\QuotationGenerate\QuotationAutoQtJobState;
use Illuminate\Console\Command;

class EmailAutoQtCommand extends Command
{
    use DispatchesAutoQtJob;

    protected $signature = 'emailAutoQt
                            {--promo= : ID promo_pengujian hasil generateAutoQt}
                            {--limit= : Max QT yang di-email}
                            {--batch= : Chunk size (default 100)}
                            {--item-pause= : Jeda antar email detik (default 1)}
                            {--chunk-pause= : Jeda antar chunk detik (default 0)}
                            {--dry-run : Hanya simulasi}
                            {--test : Mode tes: email tidak ke customer}
                            {--email= : Email test, bisa koma. Contoh: harold@intilab.com}
                            {--direct : Langsung PHP, skip Python}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto QT step 3: kirim email untuk QT dengan promo_id yang sudah di-render';

    public function handle(): int
    {
        try {
            $promoId = $this->parsePositiveInt($this->option('promo'), '--promo');
            if ($promoId === null) {
                throw new \InvalidArgumentException('promo_id wajib. Contoh: php artisan emailAutoQt --promo=12');
            }

            $email = trim((string) ($this->option('email') ?? ''));
            $test = (bool) $this->option('test') || $email !== '';
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

            $options = [
                'phase' => QuotationAutoQtJobState::PHASE_EMAIL,
                'promo_id' => $promoId,
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
            ];
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Preflight emailAutoQt');
        $this->line('Promo ID  : ' . $promoId);
        $this->line('Mode      : ' . ($options['dry_run'] ? 'DRY RUN' : ($options['test'] ? 'TEST' : 'PRODUKSI')));
        $this->line('Limit     : ' . ($options['limit'] !== null ? $options['limit'] : 'semua QT promo ini yang sudah render & belum email'));
        if ($options['test']) {
            $this->line('Email     : ' . ($options['email'] ?: 'harold@intilab.com') . ' (bukan customer)');
        } else {
            $this->warn('Email     : produksi — ke PIC order + BCC sales/supervisor');
        }
        $this->line('Python    : ' . (!$this->option('direct') && empty($options['dry_run']) && empty($options['customer']) ? 'YA' : 'tidak'));

        return $this->dispatchAutoQt($options);
    }
}
