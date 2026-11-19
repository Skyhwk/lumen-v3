<?php

namespace App\Http\Controllers\api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ScopesClaimFeeExternalRekap
{
    /**
     * Selesai / rekap: transfer sudah dicatat (tanggal + petugas).
     * Tidak mensyaratkan filename (bukti PPh) maupun bukti_transfer — keduanya arsip opsional.
     */
    protected function applyClaimFeeExternalRekapScope(Builder $query): Builder
    {
        $query->where('status_pembayaran', 'TRANSFER')
            ->whereNotNull('transferred_by');

        if (Schema::hasColumn('claim_fee_external', 'tanggal_pembayaran')) {
            // Kolom DATE/DATETIME: jangan bandingkan dengan '' (MySQL → NULL, baris hilang dari WHERE).
            $query->whereNotNull('tanggal_pembayaran');
        }

        return $query;
    }

    /**
     * Todo opsional (gabungan): belum ada bukti transfer dan/atau bukti PPh.
     * Dipakai hanya jika satu layar menampung semua todo arsip.
     */
    protected function applyClaimFeeExternalPendingBuktiScope(Builder $query): Builder
    {
        $query->where('status_pembayaran', 'TRANSFER')
            ->whereNotNull('transferred_by');

        $this->applyClaimFeeExternalOptionalDocsIncomplete($query);

        return $query;
    }

    /**
     * Todo expense: hanya bukti transfer yang belum diunggah (upload di menu Expense).
     */
    protected function applyClaimFeeExternalPendingBuktiTransferOnlyScope(Builder $query): Builder
    {
        $query->where('status_pembayaran', 'TRANSFER')
            ->whereNotNull('transferred_by');

        if (Schema::hasColumn('claim_fee_external', 'bukti_transfer')) {
            $query->where(function ($sub) {
                $sub->whereNull('bukti_transfer')
                    ->orWhere('bukti_transfer', '=', '');
            });
        }

        return $query;
    }

    /** Klaim dengan bukti PPh dan/atau bukti transfer yang belum dilampirkan (tidak memblokir selesai). */
    protected function applyClaimFeeExternalOptionalDocsIncomplete(Builder $query): void
    {
        $hasBukti = Schema::hasColumn('claim_fee_external', 'bukti_transfer');
        $hasPph = Schema::hasColumn('claim_fee_external', 'filename');

        if (!$hasBukti && !$hasPph) {
            return;
        }

        $query->where(function ($q) use ($hasBukti, $hasPph) {
            if ($hasBukti) {
                $q->where(function ($sub) {
                    $sub->whereNull('bukti_transfer')
                        ->orWhere('bukti_transfer', '=', '');
                });
            }
            if ($hasPph) {
                if ($hasBukti) {
                    $q->orWhere(function ($sub) {
                        $sub->whereNull('filename')
                            ->orWhere('filename', '=', '');
                    });
                } else {
                    $q->where(function ($sub) {
                        $sub->whereNull('filename')
                            ->orWhere('filename', '=', '');
                    });
                }
            }
        });
    }
}