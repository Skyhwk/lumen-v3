<?php

namespace App\Services\QuotationGenerate;

use App\Jobs\CopyNonKontrakJob;
use App\Models\MasterKaryawan;
use App\Models\MasterPelanggan;
use App\Models\QuotationNonKontrak;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $marker = (string) config('quotation_auto.generate.kode_promo_marker', 'AUTO');

        $newQuotation->flag_status = 'draft';
        $newQuotation->is_approved = 1;
        $newQuotation->approved_by = $approver;
        $newQuotation->approved_at = $now;
        // PDF Administrasi memakai updated_by (RenderNonKontrak).
        $newQuotation->updated_by = $approver;
        $newQuotation->updated_at = $now;
        $newQuotation->kode_promo = $marker;
        $this->attachPromoPengujian($newQuotation, $marker);

        if (config('quotation_auto.generate.resolve_sales_from_master')) {
            $newQuotation->sales_id = $this->resolveSalesIdFromMaster($newQuotation->pelanggan_ID);
        }

        $newQuotation->save();

        return $newQuotation->fresh();
    }

    private function attachPromoPengujian(QuotationNonKontrak $quotation, string $marker): void
    {
        if (!Schema::hasTable('promo_pengujian') || !Schema::hasColumn($quotation->getTable(), 'promo_id')) {
            return;
        }

        $promo = DB::table('promo_pengujian')->where('kode_promo', $marker)->first();
        if ($promo) {
            $quotation->promo_id = $promo->id;
        }
    }

    public function resolveSalesIdFromMaster($pelangganId): int
    {
        $fallbackId = (int) config('quotation_auto.generate.fallback_sales_id', 1068);
        $pelanggan = MasterPelanggan::where('id_pelanggan', $pelangganId)
            ->where('is_active', true)
            ->first();

        $salesId = (int) ($pelanggan->sales_id ?? 0);
        if ($salesId > 0 && $this->isActiveSales($salesId)) {
            return $salesId;
        }

        return $fallbackId;
    }

    private function isActiveSales(int $salesId): bool
    {
        $query = MasterKaryawan::where('id', $salesId);
        if (Schema::hasColumn('master_karyawan', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->exists();
    }
}
