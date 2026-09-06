<?php

namespace App\Services;

use App\Models\DraftingKebijakan;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class RenderKebijakanDocumentPdf
{
    private const HTML_CHUNK_SIZE = 500000;

    private const DEFAULT_DOC_TYPE = 'KETETAPAN PERUSAHAAN';

    private const MARGIN_LEFT = 23;

    private const MARGIN_RIGHT = 12;

    private const MARGIN_TOP = 45;

    private const MARGIN_HEADER = 5;

    private const MARGIN_FOOTER = 8;

    /** 1px dalam mm (25.4 / 96) */
    private const PAGE_BORDER_WIDTH = 0.2646;

    /** Jarak garis atas frame di atas baris pertama isi dokumen (mm) */
    private const FRAME_TOP_GAP = 1.5;

    /** Tinggi teks disclaimer footer yang berada di luar frame (mm) */
    private const FOOTER_TEXT_HEIGHT = 7;

    /** Tinggi area QR di dalam kotak verifikasi (mm) */
    private const VERIFICATION_QR_HEIGHT = 30;

    /** Tinggi baris label "Verifikasi & Pengesahan" (mm) */
    private const VERIFICATION_LABEL_HEIGHT = 5;

    /** Jarak aman antara baris isi terakhir dan kotak verifikasi (mm) */
    private const CONTENT_GAP_ABOVE_FOOTER = 4;

    /**
     * Batas bawah isi dokumen dihitung dari tinggi footer supaya isi tidak pernah
     * menabrak kotak verifikasi. Perhitungan otomatis mPDF meremehkan tinggi kotak,
     * jadi tinggi tiap bagian footer dipakai langsung di sini.
     */
    private const MARGIN_BOTTOM = self::MARGIN_FOOTER
        + self::VERIFICATION_QR_HEIGHT
        + self::VERIFICATION_LABEL_HEIGHT
        + self::FOOTER_TEXT_HEIGHT
        + self::CONTENT_GAP_ABOVE_FOOTER;

    private const TUJUAN_PREFIX = 'Memberikan ketentuan mengenai ';

    private const RUANG_LINGKUP_PREFIX = 'Ketetapan ini mengatur tentang ';

    private const DEFAULT_SECTIONS = [
        ['title' => 'Tujuan', 'field' => 'tujuan', 'prefix' => self::TUJUAN_PREFIX],
        ['title' => 'Ruang Lingkup', 'field' => 'ruang_lingkup', 'prefix' => self::RUANG_LINGKUP_PREFIX],
        ['title' => 'Definisi', 'field' => 'definisi'],
        ['title' => 'Ketetapan', 'field' => 'isi_ketetapan'],
    ];

    public function render(array $meta, array $sections): string
    {
        ini_set('pcre.backtrack_limit', '10000000');
        ini_set('pcre.recursion_limit', '10000000');

        $tempImageDir = storage_path('app/tmp/kebijakan_doc_images/' . uniqid('doc_', true));
        File::makeDirectory($tempImageDir, 0755, true, true);

        try {
            $sections = $this->normalizeSections($sections, $tempImageDir);
            $normalizedMeta = $this->normalizeMeta($meta);

            $htmlHeader = view('KebijakanDocument.header', [
                'meta' => $normalizedMeta,
            ])->render();

            $htmlBody = view('KebijakanDocument.body', [
                'sections' => $sections,
            ])->render();

            $htmlFooter = view('KebijakanDocument.footer', [
                'qrPath' => $this->generateQrImage($normalizedMeta['qr_value'], $tempImageDir),
            ])->render();

            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new MpdfService([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => self::MARGIN_LEFT,
                'margin_right' => self::MARGIN_RIGHT,
                'margin_top' => self::MARGIN_TOP,
                'margin_bottom' => self::MARGIN_BOTTOM,
                'margin_header' => self::MARGIN_HEADER,
                'margin_footer' => self::MARGIN_FOOTER,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P',
                'tempDir' => storage_path('app/tmp'),
                'fontDir' => array_merge($fontDirs, [
                    __DIR__ . '/vendor/mpdf/mpdf/ttfonts',
                    resource_path('fonts/Roboto'),
                ]),
                'fontdata' => $fontData + [
                    'roboto' => [
                        'R' => 'Roboto-Regular.ttf',
                        'M' => 'Roboto-Medium.ttf',
                        'SB' => 'Roboto-SemiBold.ttf',
                        'B' => 'Roboto-Bold.ttf',
                    ],
                ],
                'default_font' => 'roboto',
            ]);

            $watermarkPath = public_path('logo-watermark.png');
            if (file_exists($watermarkPath)) {
                $mpdf->SetWatermarkImage($watermarkPath, 0.08, '', [65, 60]);
                $mpdf->showWatermarkImage = true;
            }

            $mpdf->SetHTMLHeader($htmlHeader);
            $mpdf->SetHTMLFooter($htmlFooter);
            $mpdf->WriteHTML($this->stylesheet(), HTMLParserMode::HEADER_CSS);
            $this->writeHtmlInChunks($mpdf, $htmlBody);
            $this->drawPageBorders($mpdf);

            return $mpdf->Output('', 'S');
        } finally {
            File::deleteDirectory($tempImageDir);
        }
    }

    public function renderFromDraft(DraftingKebijakan $draft): string
    {
        return $this->render(
            $this->buildMetaFromDraft($draft),
            $this->buildSectionsFromDraft($draft)
        );
    }

    public function renderFromPayload(array $payload): string
    {
        return $this->render(
            $this->buildMetaFromPayload($payload),
            $this->buildSectionsFromPayload($payload)
        );
    }

    public function buildMetaFromDraft(DraftingKebijakan $draft): array
    {
        return $this->buildMetaFromPayload([
            'doc_type_title' => self::DEFAULT_DOC_TYPE,
            'header_dokumen' => $draft->divisi_bagian,
            'no_dokumen' => $draft->no_dokumen ?? '-',
            'sub_header_dokumen' => $draft->judul,
            'tanggal_cetak' => $draft->tanggal_cetak ?? null,
            'terbitan' => $draft->terbitan ?? null,
            'revisian' => $draft->revisian ?? null,
            'cetakan' => $draft->cetakan ?? null,
        ]);
    }

    public function buildSectionsFromDraft(DraftingKebijakan $draft): array
    {
        return $this->buildSectionsFromPayload([
            'tujuan' => $draft->tujuan,
            'ruang_lingkup' => $draft->ruang_lingkup,
            'definisi' => $draft->definisi,
            'isi_ketetapan' => $draft->isi_ketetapan,
        ]);
    }

    public function buildMetaFromPayload(array $payload): array
    {
        return $this->normalizeMeta([
            'doc_type_title' => $payload['doc_type_title'] ?? self::DEFAULT_DOC_TYPE,
            'header_dokumen' => $payload['header_dokumen'] ?? $payload['divisi_bagian'] ?? '-',
            'no_dokumen' => $payload['no_dokumen'] ?? '-',
            'sub_header_dokumen' => $payload['sub_header_dokumen'] ?? $payload['judul'] ?? '-',
            'tanggal_cetak' => $payload['tanggal_cetak'] ?? null,
            'terbitan' => $payload['terbitan'] ?? null,
            'revisian' => $payload['revisian'] ?? $payload['revisi'] ?? null,
            'cetakan' => $payload['cetakan'] ?? null,
            'qr_value' => $payload['qr_value'] ?? null,
        ]);
    }

    public function buildSectionsFromPayload(array $payload): array
    {
        $sections = [];

        foreach (self::DEFAULT_SECTIONS as $definition) {
            $rawContent = $payload[$definition['field']] ?? null;
            $content = isset($definition['prefix'])
                ? $this->normalizePrefixedSectionContent($rawContent, $definition['prefix'])
                : $this->normalizeContent($rawContent);

            $sections[] = [
                'title' => $definition['title'],
                'content' => $content,
            ];
        }

        return $sections;
    }

    public function formatIndonesianDate($date): string
    {
        if (!$date) {
            return '-';
        }

        return Carbon::parse($date)->locale('id')->isoFormat('D-MMMM-Y');
    }

    private function normalizeMeta(array $meta): array
    {
        return [
            'doc_type_title' => trim((string) ($meta['doc_type_title'] ?? self::DEFAULT_DOC_TYPE)) ?: self::DEFAULT_DOC_TYPE,
            'header_dokumen' => trim((string) ($meta['header_dokumen'] ?? '-')) ?: '-',
            'no_dokumen' => trim((string) ($meta['no_dokumen'] ?? '-')) ?: '-',
            'sub_header_dokumen' => trim((string) ($meta['sub_header_dokumen'] ?? '-')) ?: '-',
            'tanggal_cetak' => $meta['tanggal_cetak'] ?? null,
            'terbitan' => $meta['terbitan'] ?? null,
            'revisian' => $meta['revisian'] ?? null,
            'cetakan' => $meta['cetakan'] ?? null,
            'qr_value' => trim((string) ($meta['qr_value'] ?? '')) ?: null,
        ];
    }

    /**
     * QR verifikasi dicetak di kotak kanan bawah setiap halaman.
     * Mengembalikan null jika nilai QR belum tersedia atau gagal dibuat.
     */
    private function generateQrImage(?string $value, string $tempDir): ?string
    {
        if (!$value) {
            return null;
        }

        $filePath = $tempDir . DIRECTORY_SEPARATOR . uniqid('qr_', true) . '.png';

        try {
            QrCode::format('png')->size(400)->margin(0)->generate($value, $filePath);
        } catch (\Throwable $th) {
            return null;
        }

        return file_exists($filePath) ? str_replace('\\', '/', $filePath) : null;
    }

    private function normalizeSections(array $sections, string $tempImageDir): array
    {
        $prefixByTitle = collect(self::DEFAULT_SECTIONS)
            ->filter(fn (array $definition) => isset($definition['prefix']))
            ->pluck('prefix', 'title')
            ->all();

        return array_map(function (array $section) use ($tempImageDir, $prefixByTitle) {
            $title = $section['title'] ?? '';
            $rawContent = $section['content'] ?? null;
            $content = isset($prefixByTitle[$title])
                ? $this->normalizePrefixedSectionContent($rawContent, $prefixByTitle[$title])
                : $this->normalizeContent($rawContent);

            return [
                'title' => $title,
                'content' => $this->extractBase64ImagesToTempFiles($content, $tempImageDir),
            ];
        }, $sections);
    }

    private function normalizePrefixedSectionContent(?string $content, string $prefix): string
    {
        $normalized = $this->normalizeContent($content);
        $plainText = trim(strip_tags(html_entity_decode($normalized)));

        if ($plainText === '' || !str_starts_with($plainText, $prefix)) {
            return $normalized;
        }

        $escapedPrefix = preg_quote($prefix, '/');
        $fixed = preg_replace(
            '/^(<p[^>]*>)\s*' . $escapedPrefix . '\s*(<(span|strong|em|u|b|i)(\s[^>]*)?>)/iu',
            '$1$2' . $prefix,
            $normalized,
            1,
            $count
        );

        return $count > 0 ? $fixed : $normalized;
    }

    private function normalizeContent(?string $content): string
    {
        $normalized = trim((string) $content);

        return $normalized !== '' ? $normalized : '<p>-</p>';
    }

    private function extractBase64ImagesToTempFiles(string $content, string $tempDir): string
    {
        return preg_replace_callback(
            '/<img\b([^>]*?)\ssrc=(["\'])data:image\/([^;]+);base64,([^"\']+)\2([^>]*)>/i',
            function (array $matches) use ($tempDir) {
                $beforeSrc = $matches[1];
                $mimeSubType = strtolower($matches[3]);
                $base64Data = preg_replace('/\s+/', '', $matches[4]);
                $afterSrc = $matches[5];

                $imageData = base64_decode($base64Data, true);
                if ($imageData === false) {
                    return $matches[0];
                }

                $extension = $mimeSubType === 'jpeg' ? 'jpg' : $mimeSubType;
                $filePath = $tempDir . DIRECTORY_SEPARATOR . uniqid('img_', true) . '.' . $extension;

                if (file_put_contents($filePath, $imageData) === false) {
                    return $matches[0];
                }

                return '<img' . $beforeSrc . ' src="' . str_replace('\\', '/', $filePath) . '"' . $afterSrc . '>';
            },
            $content
        ) ?? $content;
    }

    private function writeHtmlInChunks(MpdfService $mpdf, string $html): void
    {
        if (strlen($html) <= self::HTML_CHUNK_SIZE) {
            $mpdf->WriteHTML($html);
            return;
        }

        $parts = preg_split(
            '/(?<=<\/(?:p|div|table|tr|li|h[1-6]|ul|ol|section|article|br)>)/i',
            $html
        );

        if (!$parts) {
            $offset = 0;
            $length = strlen($html);

            while ($offset < $length) {
                $mpdf->WriteHTML(substr($html, $offset, self::HTML_CHUNK_SIZE));
                $offset += self::HTML_CHUNK_SIZE;
            }

            return;
        }

        $buffer = '';

        foreach ($parts as $part) {
            if ($buffer !== '' && strlen($buffer) + strlen($part) > self::HTML_CHUNK_SIZE) {
                $mpdf->WriteHTML($buffer);
                $buffer = '';
            }

            $buffer .= $part;
        }

        if ($buffer !== '') {
            $mpdf->WriteHTML($buffer);
        }
    }

    /**
     * mPDF tidak mendukung `border` pada @page, jadi frame halaman digambar
     * manual ke buffer tiap halaman setelah seluruh konten selesai ditulis.
     *
     * Frame membentang dari bawah tabel informasi dokumen sampai di atas teks
     * disclaimer footer, sehingga logo, judul, dan disclaimer tetap di luar frame.
     */
    private function drawPageBorders(MpdfService $mpdf): void
    {
        $lastPage = $mpdf->page;

        $x = self::MARGIN_LEFT;
        $width = $mpdf->w - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $y = $mpdf->tMargin - self::FRAME_TOP_GAP;
        $height = $mpdf->h - self::MARGIN_FOOTER - self::FOOTER_TEXT_HEIGHT - $y;

        for ($page = 1; $page <= $lastPage; $page++) {
            $mpdf->page = $page;
            $mpdf->SetLineWidth(self::PAGE_BORDER_WIDTH);
            $mpdf->SetDrawColor(0, 0, 0);
            $mpdf->Rect($x, $y, $width, $height);
        }

        $mpdf->page = $lastPage;
    }

    private function stylesheet(): string
    {
        $bodyFontSize = '13px';
        $sectionFontSize = '13px';
        $qrHeight = self::VERIFICATION_QR_HEIGHT . 'mm';
        $labelHeight = self::VERIFICATION_LABEL_HEIGHT . 'mm';

        return "
            body { font-family: roboto, sans-serif; font-size: {$bodyFontSize}; color: #333; line-height: 1.8; }
            .doc-content {
                line-height: 1.8;
                text-align: justify;
                font-size: {$bodyFontSize};
                padding: 0 8mm 0 12mm;
            }
            .section-title {
                font-weight: bold;
                text-transform: uppercase;
                margin: 12px 0 8px 0;
                font-size: {$sectionFontSize};
                line-height: 1.6;
            }
            .section-title strong,
            .section-title u {
                font-weight: bold;
                font-size: {$sectionFontSize};
            }
            .section-body,
            .section-body p,
            .section-body li,
            .section-body div,
            .section-body span,
            .section-body td,
            .section-body th {
                font-size: {$bodyFontSize} !important;
                line-height: 1.8 !important;
                color: #000 !important;
            }
            .doc-content p { margin: 0 0 8px 0; }
            .doc-content li > p { display: inline; margin: 0; padding: 0; line-height: 1.8 !important; font-size: {$bodyFontSize} !important; }
            .doc-content table { border-collapse: collapse; width: 100%; }
            .doc-content table td, .doc-content table th { border: 1px solid #000; padding: 4px; font-size: {$bodyFontSize} !important; }
            .doc-content ol, .doc-content ul { padding-left: 0.95cm; margin: 0.5em 0; }
            .doc-content li { white-space: normal !important; list-style-position: outside !important; display: list-item !important; }
            .doc-footer-text {
                text-align: center;
                font-weight: bold;
                font-size: 10px;
                color: #FF0000;
                line-height: 1.35;
                padding-top: 5px;
            }
            .doc-verification-box {
                width: 100%;
                border: 0.5pt solid #FF0000;
                border-collapse: collapse;
            }
            .doc-verification-qr {
                height: {$qrHeight};
                text-align: center;
                vertical-align: middle;
                border-bottom: 0.5pt solid #FF0000;
            }
            .doc-verification-label {
                height: {$labelHeight};
                text-align: center;
                vertical-align: middle;
                font-size: 10px;
                font-weight: bold;
                color: #FF0000;
            }
        ";
    }
}
