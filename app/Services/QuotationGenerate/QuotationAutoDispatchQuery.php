<?php

namespace App\Services\QuotationGenerate;

use App\Models\QuotationNonKontrak;
use Illuminate\Support\Collection;

class QuotationAutoDispatchQuery
{
    /**
     * Penawaran auto (kode_promo + created_by) yang belum selesai generate link/PDF dan/atau email.
     *
     * @return Collection<int, QuotationNonKontrak>
     */
    public function fetchPending(?int $limit = null, ?string $customerId = null, ?int $quotationId = null): Collection
    {
        $marker = (string) config('quotation_auto.generate.kode_promo_marker', 'AUTO');
        $createdBy = (string) config('quotation_auto.generate.created_by', 'Quotation Auto Generate');
        $sendEmail = (bool) config('quotation_auto.generate.send_email');

        $query = QuotationNonKontrak::query()
            ->where('is_active', 1)
            ->where('kode_promo', $marker)
            ->where('created_by', $createdBy);

        if ($customerId !== null && $customerId !== '') {
            $query->where('pelanggan_ID', $customerId);
        }
        if ($quotationId !== null && $quotationId > 0) {
            $query->where('id', $quotationId);
        }

        $query->where(function ($outer) use ($sendEmail) {
            $outer->where(function ($needsGenerate) {
                $needsGenerate->where('is_generated', '!=', 1)
                    ->orWhereNull('is_generated');
            });

            if ($sendEmail) {
                $outer->orWhere(function ($needsEmail) {
                    $needsEmail->where('is_generated', 1)
                        ->where(function ($notEmailed) {
                            $notEmailed->where('is_emailed', '!=', 1)
                                ->orWhereNull('is_emailed');
                        });
                });
            }
        });

        $query->orderBy('created_at')->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
