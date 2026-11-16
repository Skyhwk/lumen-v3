<?php

namespace App\Services\QuotationGenerate;

use App\Models\GenerateLink;
use App\Models\JobTask;
use App\Models\QuotationNonKontrak;
use App\Services\GenerateQrDocument;
use App\Services\GenerateToken;
use App\Services\RenderNonKontrak;
use App\Support\QuotationDocumentSlug;
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

        return $this->portalAuthBaseWithToken((string) $link->token);
    }

    /**
     * Link perantara di portal (tracking buka email) — slug: "/" diganti "-".
     */
    public function portalTrackingLink(QuotationNonKontrak $quotation): string
    {
        if (empty($quotation->no_document)) {
            throw new \RuntimeException('no_document kosong untuk quotation id ' . $quotation->id);
        }

        $base = $this->portalTrackingBase();
        $path = (string) config('quotation_auto.link_tracking.portal_redirect_path', '/public/auth/redirect');
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        $slug = QuotationDocumentSlug::fromDocumentNumber((string) $quotation->no_document);

        return $base . $path . '?no_quotation=' . rawurlencode($slug);
    }

    /**
     * URL penawaran asli di portal (setelah tracking redirect).
     */
    public function portalAuthBaseWithToken(string $token): string
    {
        $base = $this->portalAuthBase();

        return $base . ltrim($token, '/');
    }

    private function portalAuthBase(): string
    {
        $base = rtrim((string) config('quotation_auto.link_tracking.portal_auth_base', ''), '/');
        if ($base === '') {
            throw new \RuntimeException(
                'PORTALV3_LINK (atau PORTAL_API) belum dikonfigurasi di .env untuk redirect penawaran.'
            );
        }

        return $base . '/';
    }

    private function portalTrackingBase(): string
    {
        $base = rtrim((string) config('quotation_auto.link_tracking.portal_base', ''), '/');
        if ($base !== '') {
            return $base;
        }

        $authBase = rtrim((string) config('quotation_auto.link_tracking.portal_auth_base', ''), '/');
        if ($authBase !== '') {
            return preg_replace('#/public/auth/?$#', '', $authBase) ?: $authBase;
        }

        throw new \RuntimeException(
            'PORTALV4 atau PORTALV3_LINK belum dikonfigurasi di .env untuk link tracking quotation auto.'
        );
    }
}
