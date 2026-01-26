<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class RenderDokumenBap
{
    public function execute($data)
    {
        $dir = public_path('dokumen/BAP/');
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $view = 'DokumenBap';
        $cleanNoDoc = str_replace('/', '_', $data->no_document);
        $filename = $cleanNoDoc . '.pdf';
        $filePath = $dir . '/' . $filename;

        $htmlBody = view($view . '.body', ['data' => $data])->render();
        $htmlHeader = view($view . '.header', ['data' => $data])->render();
        $htmlFooter = self::footer('');
        // $htmlFooter = view($view . '.footer', ['data' => $data])->render();

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \App\Services\MpdfService as Mpdf([
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
                    'BI'  => 'Roboto-BoldItalic.ttf',
                ]
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
                'print-highres' => true
            ]
        );

        $mpdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
        $mpdf->showWatermarkImage = true;
        $mpdf->showWatermarkText = true;
        $mpdf->keep_table_proportions = true;

        $mpdf->SetHTMLHeader($htmlHeader);
        $mpdf->setHTMLFooter($htmlFooter);
        $mpdf->WriteHTML(self::generateStylesheet(), \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($htmlBody);

        $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);
        return $filename;
    }

    private function footer($qr_img)
    {
        return '<table width="100%" style="font-size:6pt; font-style:italic; font-family:serif; border-top:1px solid #000; padding-top:4px;">
                <tr>
                    <td width="30%" align="left">
                    PT Inti Surya Laboratorium <br>
                    Ruko Icon Business Park Blok O No.5-6 BSD City, Jl. BSD Raya Utama, Cisauk,
                    Sampora Kab. Tangerang 15341 021-5089-8988/89
                    contact@intilab.com
                    </td>
                    <td width="40%" align="center">
                    Dokumen ini diterbitkan otomatis oleh sistem
                    </td>
                    <td width="30%" align="right">
                    ' . $qr_img . '
                    </td>
                </tr>
                </table>';
    }

    private function generateStylesheet()
    {
        return  ".custom {
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
                }
                .custom4 {
                    padding: 3px;
                    border: 1px solid #000000;
                    font-weight: bold;
                    font-size: 9px;
                }
                .custom5 {
                    text-align: left;
                }
                .custom6 {
                    padding: 3px;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                }
                .custom7 {
                    text-align: center;
                    border: 1px solid #000000;
                    font-weight: bold;
                    font-size: 9px;
                }
                .custom8 {
                    text-align: center;
                    font-style: italic;
                    border: 1px solid #000000;
                    font-weight: bold;
                    font-size: 9px;
                }
                .custom9 {
                    padding: 3px;
                    text-align: center;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px solid #000000;
                    font-size: 9px;
                }
                .pd-5-dot-center {
                    padding: 8px;
                    text-align: center;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px dotted #000000;
                    font-size: 9px;
                }
                .pd-5-dot-left {
                    padding: 8px;
                    text-align: left;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px dotted #000000;
                    font-size: 9px;
                }
                .pd-5-solid-left {
                    padding: 8px;
                    text-align: left;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px solid #000000;
                    font-size: 9px;
                }
                .pd-5-solid-center {
                    padding: 8px;
                    text-align: center;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px solid #000000;
                    font-size: 9px;
                }
                .pd-5-solid-top-center {
                    padding: 8px;
                    text-align: center;
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px solid #000000;
                    border-top: 1px solid #000000;
                    font-size: 9px;
                    font-weight: bold;
                }
                .bordered{
                    border-left: 1px solid #000000;
                    border-right: 1px solid #000000;
                    border-bottom: 1px solid #000000;
                    border-top: 1px solid #000000;
                    font-size: 9px;
                }
                .right {
                    float: right;
                    width: 40%;
                    height: 100%;
                }
                .left {
                    float: left;
                    width: 59%;
                }
                .left2 {
                    float: left;
                    width: 69%;
                }";
    }
}
