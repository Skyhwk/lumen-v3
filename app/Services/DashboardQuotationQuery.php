<?php

namespace App\Services;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardQuotationQuery
{
    public static function excludeAutoPromo($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('kode_promo')
                ->orWhereRaw("TRIM(kode_promo) = ''")
                ->orWhereRaw("LOWER(kode_promo) NOT LIKE ?", ['%auto%']);
        });
    }

    /**
     * @param  int[]|null  $salesIds
     */
    public static function collectQuotes(?array $salesIds, Carbon $startDate, Carbon $endDate)
    {
        $quotes = collect([QuotationNonKontrak::class, QuotationKontrakH::class])
            ->flatMap(function ($model) use ($salesIds, $startDate, $endDate) {
                $query = $model::query()
                    ->where('is_active', 1)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($salesIds !== null, fn ($q) => $q->whereIn('sales_id', $salesIds));
                self::excludeAutoPromo($query);

                return $query->get([
                    'no_document',
                    'pelanggan_ID',
                    'flag_status',
                    'status_quotation',
                    'kode_promo',
                    'promo_id',
                    'total_discount_promo',
                    'biaya_akhir',
                    'tanggal_penawaran',
                ]);
            })
            ->map(function ($quote) {
                $flag = strtolower(trim((string) $quote->flag_status));
                $status = strtolower(trim((string) $quote->status_quotation));
                $category = $flag === 'ordered' ? 'ordered'
                    : ($flag === 'void' ? 'void' : (in_array($status, ['cold', 'warm', 'hot'], true) ? $status : 'no_status'));

                return [
                    'no_document' => $quote->no_document,
                    'pelanggan_id' => $quote->pelanggan_ID,
                    'flag_status' => $flag,
                    'category' => $category,
                    'amount' => (float) ($quote->biaya_akhir ?? 0),
                    'has_promo' => filled($quote->kode_promo) || filled($quote->promo_id) || (float) ($quote->total_discount_promo ?? 0) > 0,
                    'is_revisi' => (bool) preg_match('/R\d+$/i', (string) $quote->no_document),
                    'tanggal_penawaran' => $quote->tanggal_penawaran,
                ];
            })
            ->values();

        $customerIds = $quotes->pluck('pelanggan_id')->filter()->unique()->values();
        $firstOrders = $customerIds->isEmpty() ? collect() : DB::table('order_header')
            ->where('is_active', 1)
            ->whereIn('id_pelanggan', $customerIds)
            ->selectRaw('id_pelanggan, MIN(tanggal_order) as first_order_date')
            ->groupBy('id_pelanggan')
            ->pluck('first_order_date', 'id_pelanggan');

        return $quotes->map(function ($quote) use ($firstOrders) {
            $firstOrder = $firstOrders->get($quote['pelanggan_id']);
            $quote['customer_type'] = $firstOrder && Carbon::parse($firstOrder)->lt(Carbon::parse($quote['tanggal_penawaran']))
                ? 'repeat'
                : 'new';

            return $quote;
        });
    }

    /**
     * @param  int[]|null  $salesIds
     */
    public static function headingTotals(?array $salesIds, Carbon $startDate, Carbon $endDate): array
    {
        $quotes = self::collectQuotes($salesIds, $startDate, $endDate);
        $ordered = $quotes->where('category', 'ordered');

        return [
            'quote_total' => $quotes->count(),
            'quote_new' => $quotes->where('customer_type', 'new')->count(),
            'quote_exist' => $quotes->where('customer_type', 'repeat')->count(),
            'order_total' => $ordered->count(),
            'order_new' => $ordered->where('customer_type', 'new')->count(),
            'order_exist' => $ordered->where('customer_type', 'repeat')->count(),
        ];
    }
}
