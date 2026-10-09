<?php

namespace App\Services;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
class DashboardQuotationNoStatusService
{
    public function resolveStatus($quote): string
    {
        $flag = strtolower(trim((string) $quote->flag_status));
        $status = strtolower(trim((string) $quote->status_quotation));

        if ($flag === 'ordered') {
            return 'ordered';
        }

        if ($flag === 'void') {
            return 'void';
        }

        if (in_array($status, ['cold', 'warm', 'hot'], true)) {
            return $status;
        }

        return 'no_status';
    }

    /**
     * @param  int[]|null  $salesIds  null = semua sales (filter SMS mode all)
     */
    public function list(?array $salesIds, Carbon $startDate, Carbon $endDate): array
    {
        return collect([QuotationNonKontrak::class, QuotationKontrakH::class])
            ->flatMap(function ($model) use ($salesIds, $startDate, $endDate) {
                return $model::query()
                    ->with([
                        'pelanggan:id_pelanggan,nama_pelanggan',
                        'sales:id,nama_lengkap',
                    ])
                    ->where('is_active', 1)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($salesIds !== null, fn ($query) => $query->whereIn('sales_id', $salesIds))
                    ->get(['no_document', 'pelanggan_ID', 'flag_status', 'status_quotation', 'sales_id']);
            })
            ->filter(fn ($quote) => $this->resolveStatus($quote) === 'no_status')
            ->unique('no_document')
            ->sortBy('no_document')
            ->values()
            ->map(fn ($quote) => [
                'no_document' => $quote->no_document,
                'nama_perusahaan' => optional($quote->pelanggan)->nama_pelanggan ?: '-',
                'sales_penanggung_jawab' => optional($quote->sales)->nama_lengkap ?: '-',
            ])
            ->all();
    }
}
