<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class RenderDokumenSkhpOnthespot
{
    public function execute($data, $hasilUji, $qr)
    {
        $dir = public_path('dokumen/SkhpOnthespot/');
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $view = 'DokumenSkhpOnthespot';
        $cleanNoDoc = str_replace('/', '_', $data->no_document);
        $filename = $cleanNoDoc . '.pdf';
        $filePath = $dir . '/' . $filename;

        $htmlHeader = view($view . '.header', ['data' => $data])->render();
        $htmlBody = view($view . '.body', [
            'data' => $data,
            'hasilUji' => $hasilUji,
            'qr' => $qr,
        ])->render();

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \App\Services\MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 3,
            'margin_bottom' => 3,
            'margin_footer' => 3,
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
            'orientation' => 'P',
            'fontDir' => array_merge($fontDirs, [
                __DIR__ . '/vendor/mpdf/mpdf/ttfonts',
            ]),
            'fontdata' => $fontData + [
                'roboto' => [
                    'R'  => 'Roboto-Regular.ttf',
                    'M'  => 'Roboto-Medium.ttf',
                    'SB' => 'Roboto-SemiBold.ttf',
                    'B'  => 'Roboto-Bold.ttf',
                    'BI' => 'Roboto-BoldItalic.ttf',
                ],
            ],
        ]);

        $mpdf->SetProtection(
            ['print'],
            '',
            'skyhwk12',
            128,
            [
                'copy' => false,
                'modify' => false,
                'print' => true,
                'annot-forms' => false,
                'fill-forms' => false,
                'extract' => false,
                'assemble' => false,
                'print-highres' => true,
            ]
        );

        $mpdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', [65, 60]);
        $mpdf->showWatermarkImage = true;
        $mpdf->showWatermarkText = true;
        $mpdf->keep_table_proportions = true;

        $mpdf->SetHTMLHeader($htmlHeader);
        $mpdf->WriteHTML($this->generateStylesheet(), \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($htmlBody);

        $footer = [
            'odd' => [
                'C' => [
                    'content' => 'Hal {PAGENO} dari {nbpg}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#606060',
                ],
                'R' => [
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000000',
                ],
                'L' => [
                    'content' => 'PT Inti Surya Laboratorium<br>Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk,<br>Sampora Kab. Tangerang 15341 021-5089-8988/89 contact@intilab.com',
                    'font-size' => 4,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000000',
                ],
                'line' => -1,
            ],
        ];

        $mpdf->setFooter($footer);
        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

        return $filename;
    }

    private function generateStylesheet()
    {
        return ".custom {
                    padding: 3px;
                    text-align: center;
                    border: 1px solid #000000;
                    font-weight: bold;
                    font-size: 9px;
                }
                .custom1 {
                    padding: 3px;
                    text-align: left;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px dotted #5b5b5b;
                    font-size: 9px;
                }
                .custom2 {
                    padding: 3px;
                    text-align: center;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px dotted #5b5b5b;
                    font-size: 9px;
                }
                .custom3 {
                    padding: 3px;
                    text-align: center;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px solid #000000;
                    font-size: 9px;
                }";
    }
}
