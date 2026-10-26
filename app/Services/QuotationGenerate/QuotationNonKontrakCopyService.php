<?php

namespace App\Services\QuotationGenerate;

use App\Jobs\CopyNonKontrakJob;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;

class QuotationNonKontrakCopyService
{
    /**
     * Copy record via CopyNonKontrakJob (sama QtOrderedController::copy non_kontrak),
     * lalu set metadata approval untuk pipeline auto-generate.
     */
    public function copyFromSource(int $sourceQuotationId, string $createdBy): QuotationNonKontrak
    {
        $source = QuotationNonKontrak::where('id', $sourceQuotationId)
            ->where('is_active', 1)
            ->firstOrFail();

        // idcabang tidak dipakai untuk nomor urut (penomoran global, bukan per cabang).
        $job = new CopyNonKontrakJob(0, $createdBy, $sourceQuotationId);
        $newQuotation = $job->handle();

        if (!$newQuotation instanceof QuotationNonKontrak) {
            throw new \RuntimeException('CopyNonKontrakJob tidak mengembalikan penawaran baru.');
        }

        $approver = (string) config('quotation_auto.generate.approved_by', 'Lani Febriana Safitri');
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $newQuotation->flag_status = 'draft';
        $newQuotation->is_approved = 1;
        $newQuotation->approved_by = $approver;
        $newQuotation->approved_at = $now;
        // PDF Administrasi memakai updated_by (RenderNonKontrak).
        $newQuotation->updated_by = $approver;
        $newQuotation->updated_at = $now;
        $newQuotation->kode_promo = (string) config('quotation_auto.generate.kode_promo_marker', 'AUTO');
        $newQuotation->save();

        return $newQuotation->fresh();
    }
}
