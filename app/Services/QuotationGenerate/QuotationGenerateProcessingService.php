<?php

namespace App\Services\QuotationGenerate;

use App\Models\QuotationNonKontrak;

class QuotationGenerateProcessingService
{
    /** @var QuotationNonKontrakCopyService */
    private $copyService;

    /** @var QuotationGenerateLinkService */
    private $linkService;

    /** @var QuotationGenerateEmailService */
    private $emailService;

    public function __construct(
        QuotationNonKontrakCopyService $copyService = null,
        QuotationGenerateLinkService $linkService = null,
        QuotationGenerateEmailService $emailService = null
    ) {
        $this->copyService = $copyService ?: new QuotationNonKontrakCopyService();
        $this->linkService = $linkService ?: new QuotationGenerateLinkService();
        $this->emailService = $emailService ?: new QuotationGenerateEmailService($this->linkService);
    }

    /**
     * @param  object{reference_quotation_id:int,customer_id:string,customer_type:string}  $candidate
     * @return array{success:bool,source_quotation_id:int,new_quotation_id?:int,new_no_document?:string,message:string}
     */
    public function processEligibleCandidate(object $candidate, bool $dryRun = false): array
    {
        $createResult = $this->createFromCandidate($candidate, $dryRun);
        if ($dryRun) {
            return $createResult;
        }

        $newQuotation = QuotationNonKontrak::findOrFail((int) $createResult['new_quotation_id']);

        return $this->dispatchQuotation($newQuotation, $dryRun, (int) $createResult['source_quotation_id']);
    }

    /**
     * Step 1: copy + approve + kode_promo AUTO (tanpa render/link/email).
     *
     * @param  object{reference_quotation_id:int,customer_id?:string}  $candidate
     * @return array{success:bool,source_quotation_id:int,new_quotation_id?:int,new_no_document?:string,message:string}
     */
    public function createFromCandidate(object $candidate, bool $dryRun = false): array
    {
        $sourceId = (int) $candidate->reference_quotation_id;
        $customerId = isset($candidate->customer_id) ? (string) $candidate->customer_id : '';

        $this->assertLatestSourceQuotationWithoutKodePromo($sourceId, $customerId);

        if ($dryRun) {
            return [
                'success' => true,
                'source_quotation_id' => $sourceId,
                'message' => 'DRY RUN: akan copy dari quotation id ' . $sourceId,
            ];
        }

        $createdBy = (string) config('quotation_auto.generate.created_by');
        $newQuotation = $this->copyService->copyFromSource($sourceId, $createdBy);

        return [
            'success' => true,
            'source_quotation_id' => $sourceId,
            'new_quotation_id' => (int) $newQuotation->id,
            'new_no_document' => (string) $newQuotation->no_document,
            'message' => 'Penawaran ' . $newQuotation->no_document . ' berhasil dibuat (copy + approve, belum render/email)',
        ];
    }

    /**
     * Step 2: render PDF, generate link/token, optional email.
     *
     * @return array{success:bool,source_quotation_id?:int,new_quotation_id:int,new_no_document:string,pdf_path?:string,message:string}
     */
    public function dispatchQuotation(
        QuotationNonKontrak $quotation,
        bool $dryRun = false,
        ?int $sourceQuotationId = null
    ): array {
        $generatedBy = (string) config('quotation_auto.generate.generated_by');
        $emailedBy = (string) config('quotation_auto.generate.emailed_by');
        $marker = (string) config('quotation_auto.generate.kode_promo_marker', 'AUTO');

        if (trim((string) ($quotation->kode_promo ?? '')) !== $marker) {
            throw new \RuntimeException(
                'Quotation ' . $quotation->no_document . ' bukan penawaran auto (kode_promo bukan ' . $marker . ').'
            );
        }

        if ($dryRun) {
            $parts = [];
            if ((int) $quotation->is_generated !== 1) {
                $parts[] = 'render PDF + link';
            }
            if (config('quotation_auto.generate.send_email') && (int) $quotation->is_emailed !== 1) {
                $parts[] = 'email';
            }

            return [
                'success' => true,
                'new_quotation_id' => (int) $quotation->id,
                'new_no_document' => (string) $quotation->no_document,
                'message' => 'DRY RUN: ' . $quotation->no_document . ' → ' . ($parts === [] ? 'tidak ada langkah' : implode(', ', $parts)),
            ];
        }

        if ((int) $quotation->is_generated !== 1) {
            $quotation = $this->linkService->generateForNonKontrak($quotation, $generatedBy);
        }

        $emailNote = '';
        if (config('quotation_auto.generate.send_email')) {
            if ((int) $quotation->is_emailed !== 1) {
                $quotation = $this->emailService->sendNonKontrakPenawaran($quotation, $emailedBy);
                $emailNote = ', dan dikirim email';
            }
        } else {
            $emailNote = ' (email dilewati — quotation_auto.generate.send_email=false)';
        }

        $pdfPath = public_path('quotation/' . $quotation->filename);

        $result = [
            'success' => true,
            'new_quotation_id' => (int) $quotation->id,
            'new_no_document' => (string) $quotation->no_document,
            'pdf_path' => $pdfPath,
            'message' => 'Penawaran ' . $quotation->no_document . ' berhasil di-generate (PDF + link)'
                . $emailNote . '. PDF: ' . $pdfPath,
        ];
        if ($sourceQuotationId !== null) {
            $result['source_quotation_id'] = $sourceQuotationId;
        }

        return $result;
    }

    public function findSourceQuotation(int $sourceQuotationId): ?QuotationNonKontrak
    {
        return QuotationNonKontrak::where('id', $sourceQuotationId)->where('is_active', 1)->first();
    }

    private function assertLatestSourceQuotationWithoutKodePromo(int $sourceQuotationId, string $customerId): void
    {
        $source = $this->findSourceQuotation($sourceQuotationId);
        if ($source === null) {
            throw new \RuntimeException('Quotation sumber id ' . $sourceQuotationId . ' tidak ditemukan.');
        }

        $kodePromo = trim((string) ($source->kode_promo ?? ''));
        if ($kodePromo !== '') {
            throw new \RuntimeException(sprintf(
                'Customer %s ditolak: penawaran sumber %s masih memiliki kode_promo (%s).',
                $customerId !== '' ? $customerId : $source->pelanggan_ID,
                $source->no_document,
                $kodePromo
            ));
        }
    }
}
