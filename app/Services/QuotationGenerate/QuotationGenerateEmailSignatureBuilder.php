<?php

namespace App\Services\QuotationGenerate;

use Illuminate\Support\Facades\View;

class QuotationGenerateEmailSignatureBuilder
{
    /** QR & logo footer selalu data URI base64 (selaras ComposeMail Qt Approved). */

    /**
     * @param int|null $salesId Diabaikan untuk QR; tetap dipakai caller email service.
     */
    public function buildForSalesId($salesId): string
    {
        $nama = (string) config('quotation_auto.generate.signature_display_name', 'Aisyah Wulandari');
        $jabatan = (string) config('quotation_auto.generate.signature_display_jabatan', 'Sales Admin Staff');

        $qrImageSrc = (string) config('quotation_auto.generate.signature_qr_data_uri', '');
        if ($qrImageSrc === '') {
            $qrImageSrc = null;
        }

        return trim(View::make('TemplateEmail.partials.quotation_email_signature', [
            'namaLengkap' => $nama,
            'namaJabatan' => $jabatan,
            'teleponPerusahaan' => (string) config('quotation_auto.generate.signature_company_phone', '021 50898988'),
            'emailPerusahaan' => (string) config('quotation_auto.generate.signature_company_email', 'admsales01@intilab.com'),
            'websitePerusahaan' => (string) config('quotation_auto.generate.signature_company_website', 'www.intilab.com'),
            'qrImageSrc' => $qrImageSrc,
            'footerLogoSrcs' => $this->footerLogoInlineSrcs(),
        ])->render());
    }

    /**
     * @return array<int, array{src: string, width: int, height: int}>
     */
    private function footerLogoInlineSrcs(): array
    {
        $configured = (array) config('quotation_auto.generate.signature_footer_logos', []);
        if ($configured !== []) {
            $out = [];
            foreach ($configured as $logo) {
                $path = (string) ($logo['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $src = $this->fileToDataUri(public_path($path));
                if ($src === null) {
                    continue;
                }
                $out[] = [
                    'src' => $src,
                    'width' => (int) ($logo['width'] ?? 0),
                    'height' => (int) ($logo['height'] ?? 63),
                ];
            }

            return $out;
        }

        $defaults = [
            ['file' => 'img/email-signature/logo-isl.png', 'width' => 220, 'height' => 63],
            ['file' => 'img/email-signature/logo-kan.png', 'width' => 147, 'height' => 63],
            ['file' => 'img/email-signature/logo-kemnaker.png', 'width' => 52, 'height' => 63],
            ['file' => 'img/email-signature/logo-tree.png', 'width' => 56, 'height' => 63],
        ];

        $out = [];
        foreach ($defaults as $item) {
            $src = $this->fileToDataUri(public_path($item['file']));
            if ($src === null) {
                continue;
            }
            $out[] = [
                'src' => $src,
                'width' => $item['width'],
                'height' => $item['height'],
            ];
        }

        return $out;
    }

    private function fileToDataUri(string $absolutePath): ?string
    {
        if (!is_readable($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolutePath));
    }
}
