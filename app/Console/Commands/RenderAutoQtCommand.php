<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DispatchesAutoQtJob;
use App\Services\QuotationGenerate\QuotationAutoQtJobState;
use Illuminate\Console\Command;

class RenderAutoQtCommand extends Command
{
    use DispatchesAutoQtJob;

    protected $signature = 'renderAutoQt
                            {--promo= : ID promo_pengujian hasil generateAutoQt}
                            {--limit= : Max QT yang di-render}
                            {--batch= : Chunk size (default 100)}
                            {--dry-run : Hanya simulasi}
                            {--direct : Langsung PHP, skip Python}
                            {--customer= : Proses satu id_pelanggan saja}';

    protected $description = 'Auto QT step 2: render PDF + link untuk QT dengan promo_id hasil generateAutoQt';

    public function handle(): int
    {
        try {
            $promoId = $this->parsePositiveInt($this->option('promo'), '--promo');
            if ($promoId === null) {
                throw new \InvalidArgumentException('promo_id wajib. Contoh: php artisan renderAutoQt --promo=12');
            }

            $options = [
                'phase' => QuotationAutoQtJobState::PHASE_RENDER,
                'promo_id' => $promoId,
                'test' => false,
                'dry_run' => (bool) $this->option('dry-run'),
                'limit' => $this->parsePositiveInt($this->option('limit'), '--limit'),
                'customer' => $this->option('customer') !== null && $this->option('customer') !== ''
                    ? (string) $this->option('customer')
                    : null,
                'batch' => $this->option('batch') !== null ? (int) $this->option('batch') : null,
            ];
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Preflight renderAutoQt');
        $this->line('Promo ID  : ' . $promoId);
        $this->line('Mode      : ' . ($options['dry_run'] ? 'DRY RUN' : 'RENDER PDF'));
        $this->line('Limit     : ' . ($options['limit'] !== null ? $options['limit'] : 'semua QT promo ini yang belum render'));
        $this->line('Python    : ' . (!$this->option('direct') && empty($options['dry_run']) && empty($options['customer']) ? 'YA' : 'tidak'));

        return $this->dispatchAutoQt($options);
    }
}
