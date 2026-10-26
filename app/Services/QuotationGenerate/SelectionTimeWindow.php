<?php

namespace App\Services\QuotationGenerate;

use Carbon\Carbon;

class SelectionTimeWindow
{
    /** @var string */
    private $timezone;

    /** @var int */
    private $quotationMonths;

    /** @var int */
    private $orderMonths;

    public function __construct(
        string $timezone = 'Asia/Jakarta',
        int $quotationMonths = 3,
        int $orderMonths = 6
    ) {
        $this->timezone = $timezone;
        $this->quotationMonths = $quotationMonths;
        $this->orderMonths = $orderMonths;
    }

    public static function fromConfig(): self
    {
        $config = config('quotation_auto.discovery', []);

        return new self(
            (string) ($config['timezone'] ?? config('quotation_auto.timezone', 'Asia/Jakarta')),
            (int) ($config['quotation_months'] ?? 3),
            (int) ($config['order_months'] ?? 6)
        );
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function quotationSince(?Carbon $now = null): Carbon
    {
        $now = $this->anchor($now);

        return $now->copy()->subMonths($this->quotationMonths);
    }

    public function orderSince(?Carbon $now = null): Carbon
    {
        $now = $this->anchor($now);

        return $now->copy()->subMonths($this->orderMonths);
    }

    public function quotationMonths(): int
    {
        return $this->quotationMonths;
    }

    public function orderMonths(): int
    {
        return $this->orderMonths;
    }

    private function anchor(?Carbon $now): Carbon
    {
        return ($now ?? Carbon::now($this->timezone))->timezone($this->timezone);
    }
}
