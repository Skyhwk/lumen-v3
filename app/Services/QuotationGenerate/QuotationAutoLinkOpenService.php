<?php

namespace App\Services\QuotationGenerate;

use App\Models\QuotationAutoLinkOpen;
use App\Models\QuotationNonKontrak;
use App\Support\QuotationDocumentSlug;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationAutoLinkOpenService
{
    /** @var QuotationGenerateLinkService */
    private $linkService;

    public function __construct(?QuotationGenerateLinkService $linkService = null)
    {
        $this->linkService = $linkService ?: new QuotationGenerateLinkService();
    }

    /**
     * @return array{redirect_url: string, open_count: int, first_open: bool}
     */
    public function recordOpenAndResolveRedirectUrl(string $slug, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $slug = QuotationDocumentSlug::normalizeIncoming($slug);
        if ($slug === '') {
            throw new \InvalidArgumentException('Nomor penawaran tidak valid.');
        }

        $quotation = $this->findQuotationBySlug($slug);
        if (!$quotation) {
            throw new \RuntimeException('Penawaran tidak ditemukan.');
        }

        if (!$this->isTrackedAutoQuotation($quotation)) {
            throw new \RuntimeException('Penawaran bukan hasil quotation auto.');
        }

        $redirectUrl = $this->linkService->portalLink($quotation);
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 2000) : null;

        $firstOpen = false;
        $openCount = 1;

        DB::transaction(function () use ($quotation, $now, $ipAddress, $userAgent, &$firstOpen, &$openCount) {
            $existing = QuotationAutoLinkOpen::where('quotation_id', $quotation->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->last_opened_at = $now;
                $existing->open_count = (int) $existing->open_count + 1;
                $existing->last_ip_address = $ipAddress;
                $existing->last_user_agent = $userAgent;
                $existing->save();
                $openCount = (int) $existing->open_count;

                return;
            }

            $firstOpen = true;
            QuotationAutoLinkOpen::create([
                'quotation_id' => $quotation->id,
                'no_document' => $quotation->no_document,
                'promo_id' => $quotation->promo_id,
                'kode_promo' => $quotation->kode_promo,
                'nama_perusahaan' => $quotation->nama_perusahaan,
                'nama_pic_order' => $quotation->nama_pic_order,
                'email_pic_order' => $quotation->email_pic_order,
                'no_telpon_pic_order' => $quotation->no_pic_order,
                'sales_id' => $quotation->sales_id,
                'first_opened_at' => $now,
                'last_opened_at' => $now,
                'open_count' => 1,
                'last_ip_address' => $ipAddress,
                'last_user_agent' => $userAgent,
            ]);
        });

        return [
            'redirect_url' => $redirectUrl,
            'open_count' => $openCount,
            'first_open' => $firstOpen,
        ];
    }

    private function findQuotationBySlug(string $slug): ?QuotationNonKontrak
    {
        return QuotationNonKontrak::query()
            ->where('is_active', 1)
            ->whereRaw("REPLACE(no_document, '/', '-') = ?", [$slug])
            ->orderByDesc('id')
            ->first();
    }

    private function isTrackedAutoQuotation(QuotationNonKontrak $quotation): bool
    {
        $kode = trim((string) ($quotation->kode_promo ?? ''));
        if ($kode === '') {
            return false;
        }

        $markers = [(string) config('quotation_auto.generate.kode_promo_marker', 'AUTO')];
        foreach ((array) config('quotation_auto.campaigns', []) as $campaign) {
            if (!empty($campaign['kode_promo_marker'])) {
                $markers[] = (string) $campaign['kode_promo_marker'];
            }
        }
        $markers = array_values(array_unique(array_filter($markers)));

        if (in_array($kode, $markers, true)) {
            return true;
        }

        return strpos($kode, 'Auto_') === 0;
    }
}
