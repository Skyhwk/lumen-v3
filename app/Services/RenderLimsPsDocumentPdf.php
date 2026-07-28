<?php

namespace App\Services;

use App\Models\LimsDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Mpdf\HTMLParserMode;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class RenderLimsPsDocumentPdf
{
    private const HTML_CHUNK_SIZE = 500000;

    public function render(LimsDocument $document, string $content): string
    {
        ini_set('pcre.backtrack_limit', '10000000');
        ini_set('pcre.recursion_limit', '10000000');

        $document->loadMissing('approvals');

        $tempImageDir = storage_path('app/tmp/lims_ps_images/' . uniqid('doc_', true));
        File::makeDirectory($tempImageDir, 0755, true, true);

        try {
            $content = $this->extractBase64ImagesToTempFiles($content, $tempImageDir);

            $htmlHeader = view('LimsDocument.header', ['document' => $document])->render();
            $htmlBody = view('LimsDocument.body', [
                'document' => $document,
                'content' => $content,
            ])->render();

            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new MpdfService([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 45,
                'margin_bottom' => 15,
                'margin_header' => 5,
                'setAutoTopMargin' => 'stretch',
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
            $mpdf->WriteHTML($this->stylesheet(), HTMLParserMode::HEADER_CSS);
            $this->writeHtmlInChunks($mpdf, $htmlBody);

            return $mpdf->Output('', 'S');
        } finally {
            File::deleteDirectory($tempImageDir);
        }
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

    public function formatIndonesianDate($date): string
    {
        if (!$date) {
            return '-';
        }

        return Carbon::parse($date)->locale('id')->isoFormat('D-MMMM-Y');
    }

    private function stylesheet(): string
    {
        return '
            body { font-family: roboto, sans-serif; font-size: 11px; color: #333; line-height: 1.8; }
            .doc-content { line-height: 1.8; text-align: justify; }
            .doc-content p,
            .doc-content li,
            .doc-content div,
            .doc-content span,
            .doc-content td,
            .doc-content th,
            .doc-content h1,
            .doc-content h2,
            .doc-content h3,
            .doc-content h4,
            .doc-content h5,
            .doc-content h6 { line-height: 1.8 !important; }
            .doc-content p { margin: 0 0 8px 0; }
            .doc-content li > p { display: inline; margin: 0; padding: 0; line-height: 1.8 !important; }
            .doc-content table { border-collapse: collapse; width: 100%; }
            .doc-content table td, .doc-content table th { border: 1px solid #000; padding: 4px; }
            .auth-section { margin-top: 24px; page-break-inside: avoid; }
            .auth-title { font-weight: bold; margin-bottom: 8px; }
            .auth-note { font-size: 10px; margin-bottom: 12px; line-height: 1.4; }
            .auth-note ul { margin: 4px 0 0 18px; padding: 0; }
            .auth-table { width: 100%; border-collapse: collapse; font-size: 10px; }
            .auth-table td, .auth-table th { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
            .auth-table th { font-weight: bold; background: #f5f5f5; }
            .auth-role { font-weight: bold; width: 18%; }
        ';
    }
}
