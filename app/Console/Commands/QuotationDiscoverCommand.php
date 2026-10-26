<?php

namespace App\Console\Commands;

use App\Services\QuotationGenerate\QuotationGenerateDiscoveryService;
use App\Services\QuotationGenerate\SelectionTimeWindow;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class QuotationDiscoverCommand extends Command
{
    protected $signature = 'quotation:discover
                            {--limit= : Maximum eligible rows to display (counts still use full time window)}
                            {--batch=100 : Chunk size when printing eligible rows}
                            {--explain : Print EXPLAIN for discovery queries}';

    protected $description = 'Discover eligible customers for auto quotation (read-only, candidate-first)';

    public function handle(): int
    {
        $timeWindow = SelectionTimeWindow::fromConfig();
        $discovery = new QuotationGenerateDiscoveryService();
        $quotationSince = $timeWindow->quotationSince();
        $orderSince = $timeWindow->orderSince();

        $displayLimit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $batchSize = max(1, (int) ($this->option('batch') ?: 100));

        $this->info('Quotation discovery (read-only)');
        $this->line('Timezone           : '.$timeWindow->timezone());
        $this->line('Quotation window   : last '.$timeWindow->quotationMonths().' month(s), since '.$quotationSince->toDateTimeString());
        $this->line('Order window       : last '.$timeWindow->orderMonths().' month(s), since '.$orderSince->toDateTimeString());
        $this->newLine();

        if ($this->option('explain')) {
            $this->printExplain('NEW (quotation path)', $discovery->explainNewDiscovery($quotationSince));
            $this->printExplain('EXISTING (order path)', $discovery->explainExistingDiscovery($orderSince));
            $this->newLine();
        }

        $candidateFromQuotation = $discovery->countQuotationCandidates($quotationSince);
        $candidateFromOrder = $discovery->countOrderCandidates($orderSince);

        $eligibleNewCount = $discovery->countEligibleNew($quotationSince);
        $eligibleExistingCount = $discovery->countEligibleExisting($orderSince);

        $remainingDisplay = $displayLimit;
        $newRows = $this->fetchWithDisplayLimit(
            fn (?int $limit) => $discovery->fetchEligibleNew($quotationSince, $limit),
            $remainingDisplay
        );
        if ($displayLimit !== null) {
            $remainingDisplay = max(0, $displayLimit - $newRows->count());
        }

        $existingRows = $this->fetchWithDisplayLimit(
            fn (?int $limit) => $discovery->fetchEligibleExisting($orderSince, $limit),
            $displayLimit !== null ? ($remainingDisplay > 0 ? $remainingDisplay : 0) : null
        );

        $eligibleRows = $newRows->concat($existingRows);

        if ($eligibleRows->isEmpty()) {
            $this->warn('No ELIGIBLE candidates found in the configured time windows.');
        } else {
            $this->info('ELIGIBLE candidates'.($displayLimit ? " (showing up to {$displayLimit})" : '').':');
            $this->newLine();

            $eligibleRows->chunk($batchSize)->each(function (Collection $chunk) {
                foreach ($chunk as $row) {
                    $this->printEligibleRow($row);
                }
            });
        }

        $this->newLine();
        $this->line('================ DISCOVERY SUMMARY ================');
        $this->line(sprintf('Candidate From Quotation      : %d', $candidateFromQuotation));
        $this->line(sprintf('Candidate From Order          : %d', $candidateFromOrder));
        $this->line('');
        $this->line(sprintf('Eligible New Customer         : %d', $eligibleNewCount));
        $this->line(sprintf('Eligible Existing Customer    : %d', $eligibleExistingCount));
        $this->line('');
        $this->line('---------------------------------------------------');
        $this->line(sprintf('TOTAL ELIGIBLE                : %d', $eligibleNewCount + $eligibleExistingCount));
        $this->line('====================================================');

        return self::SUCCESS;
    }

    private function fetchWithDisplayLimit(callable $fetch, ?int $limit): Collection
    {
        if ($limit !== null && $limit <= 0) {
            return collect();
        }

        return $fetch($limit);
    }

    private function printEligibleRow(object $row): void
    {
        $this->line('Customer ID     : '.$row->customer_id);
        $this->line('Customer Name   : '.$row->customer_name);
        $this->line('Customer Type   : '.$row->customer_type);
        $this->newLine();

        if ($row->customer_type === 'NEW') {
            $this->line('Reference Order ID   : -');
            $this->line('Reference Order No   : -');
            $this->line('Order Created At     : -');
        } else {
            $this->line('Reference Order ID   : '.$row->reference_order_id);
            $this->line('Reference Order No   : '.($row->reference_order_no ?? '-'));
            $this->line('Order Created At     : '.$this->formatDateTime($row->order_created_at));
        }

        $this->newLine();
        $this->line('Reference Quotation ID : '.$row->reference_quotation_id);
        $this->line('Reference Quotation No : '.($row->reference_quotation_no ?? '-'));
        $this->line('Quotation Created At   : '.$this->formatDateTime($row->quotation_created_at));
        $this->newLine();
        $this->line('Status                 : '.$row->status);
        $this->line(str_repeat('-', 52));
        $this->newLine();
    }

    /**
     * @param  mixed  $value
     */
    private function formatDateTime($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return Carbon::parse($value)->toDateTimeString();
    }

    /**
     * @param  array<int, object>  $plans
     */
    private function printExplain(string $label, array $plans): void
    {
        $this->info("EXPLAIN — {$label}");
        $headers = ['id', 'select_type', 'table', 'type', 'possible_keys', 'key', 'rows', 'filtered', 'Extra'];
        $rows = array_map(static function ($plan) use ($headers) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $plan->{$header} ?? '';
            }

            return $line;
        }, $plans);
        $this->table($headers, $rows);
    }
}
