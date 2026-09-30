<?php

namespace App\Services\QuotationGenerate;

use App\Models\GenerateLink;
use App\Models\JobTask;
use App\Models\QuotationNonKontrak;
use App\Services\GenerateQrDocument;
use App\Services\GenerateToken;
use App\Services\RenderNonKontrak;
use Carbon\Carbon;

class QuotationGenerateLinkService
{
    /**
     * QR + render PDF (sync) + token/link — selaras QtApproveController::approve non_kontrak.
     */
    public function generateForNonKontrak(QuotationNonKontrak $quotation, string $generatedBy): QuotationNonKontrak
    {
        (new GenerateQrDocument())->insert('quotation_non_kontrak', $quotation, $generatedBy);

        JobTask::insert([
            'job' => 'RenderPdfPenawaran',
            'status' => 'processing',
            'no_document' => $quotation->no_document,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        $render = new RenderNonKontrak();
        $render->renderHeader($quotation->id, 'id');
        $render->renderHeader($quotation->id, 'en');

        $quotation = QuotationNonKontrak::findOrFail($quotation->id);

        if (empty($quotation->filename)) {
            throw new \RuntimeException('Gagal render PDF penawaran: filename kosong untuk ' . $quotation->no_document);
        }

        $pdfPath = public_path('quotation/' . $quotation->filename);
        if (!is_readable($pdfPath)) {
            throw new \RuntimeException(
                'PDF tidak ditemukan setelah render. Harus ada di: ' . $pdfPath
            );
        }

        $generateToken = new GenerateToken();
        $token = $generateToken->save('non_kontrak', $quotation, $generatedBy, 'quotation');

        $quotation->is_generated = 1;
        $quotation->generated_by = $generatedBy;
        $quotation->generated_at = Carbon::now()->format('Y-m-d H:i:s');
        $quotation->id_token = $token->id;
        $quotation->expired = $token->expired;
        $quotation->save();

        return $quotation->fresh();
    }

    public function portalLink(QuotationNonKontrak $quotation): string
    {
        $link = GenerateLink::where('id_quotation', $quotation->id)
            ->where('quotation_status', 'non_kontrak')
            ->where('type', 'quotation')
            ->orderByDesc('id')
            ->first();

        if (!$link || empty($link->token)) {
            throw new \RuntimeException('Generate link belum tersedia untuk quotation id ' . $quotation->id);
        }

        return (string) env('PORTALV3_LINK', '') . $link->token;
    }
}
