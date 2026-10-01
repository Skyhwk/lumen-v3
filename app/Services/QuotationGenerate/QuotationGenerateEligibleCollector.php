<?php

namespace App\Services\QuotationGenerate;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class QuotationGenerateEligibleCollector
{
    public function collect(
        QuotationGenerateDiscoveryService $discovery,
        Carbon $quotationSince,
        Carbon $orderSince,
        ?int $limit,
        ?string $customerFilter
    ): Collection {
        $remaining = $limit;
        $newRows = $discovery->fetchEligibleNew($quotationSince, $remaining);
        if ($limit !== null) {
            $remaining = max(0, $limit - $newRows->count());
        }

        if ($limit !== null && $remaining === 0) {
            $existingRows = collect();
        } elseif ($remaining !== null && $remaining > 0) {
            $existingRows = $discovery->fetchEligibleExisting($orderSince, $remaining);
        } else {
            $existingRows = $discovery->fetchEligibleExisting($orderSince, null);
        }

        $rows = $newRows->concat($existingRows);
        if ($limit !== null && $limit > 0) {
            $rows = $rows->take($limit)->values();
        }

        if ($customerFilter !== null && $customerFilter !== '') {
            $rows = $rows->where('customer_id', $customerFilter)->values();
        }

        return $rows;
    }
}
