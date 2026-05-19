<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class RenderLhpSar
{
    private $header;
    private $detail;

    private static $instance;

    public static function setDataHeader($value)
    {
        if (!self::$instance) self::$instance = new self();

        self::$instance->header = $value;

        return self::$instance;
    }

    public static function setDataDetail($value)
    {
        if (!self::$instance) self::$instance = new self();

        self::$instance->detail = $value;

        return self::$instance;
    }

    public function render()
    {
        if (!$this->header) throw new \Exception("Header belum diset. Gunakan setHeader() sebelum render().");
        if (!$this->detail) throw new \Exception("Detail belum diset. Gunakan setDetail() sebelum render().");

        $filename = $this->execute($this->header, $this->detail);

        self::$instance = null;

        return $filename;
    }

    private function execute($header, $detail)
    {
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();

        $mpdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_header' => 12,
            'margin_bottom' => 22,
            'margin_footer' => 8,
            'margin_top' => 27.5,
            'margin_left' => 10,
            'margin_right' => 10,
            'orientation' => 'L',
            'fontDir' => array_merge($defaultConfig['fontDir'], [__DIR__ . '/vendor/mpdf/mpdf/ttfonts']),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                'roboto' => [
                    'R'  => 'Roboto-Regular.ttf',
                    'M'  => 'Roboto-Medium.ttf',
                    'SB' => 'Roboto-SemiBold.ttf',
                    'B'  => 'Roboto-Bold.ttf',
                    'BI'  => 'Roboto-BoldItalic.ttf',
                ]
            ],
        ]);

        $stylesheet = $this->generateStylesheet();

        $parametersSar = \App\Models\ParameterSar::where('is_active', 1)->get();

        $htmlHeader = view('TemplateLHP.LHPSAR.header', compact('header', 'detail', 'parametersSar'))->render();
        $htmlBody = view('TemplateLHP.LHPSAR.left', compact('header', 'detail', 'parametersSar'))->render();
        $htmlFooter = view('TemplateLHP.LHPSAR.footer', compact('header', 'detail', 'parametersSar'))->render();

        $mpdf->SetHTMLHeader($htmlHeader);
        $mpdf->SetHTMLFooter($htmlFooter);

        $mpdf->showWatermarkImage = true;
        $mpdf->SetWatermarkImage(public_path() . "/logo-watermark.png", -1, "", [110, 35]);

        $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($htmlBody);

        $mpdf->SetProtection(['print'], '', 'skyhwk12', 128);

        $dir = public_path('dokumen/LHP-SAR/');
        if (!file_exists($dir)) mkdir($dir, 0777, true);

        $filename = 'LHP-' . str_replace("/", "-", $header->no_order) . '.pdf';

        $mpdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);

        return $filename;
    }

    private function generateStylesheet()
    {
        return "
            .custom {
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
            .pd-3-dot {
                padding: 3px;
                border-left: 1px solid #000000;
                border-right: 1px solid #000000;
                border-bottom: 1px dotted #000000;
                font-size: 9px;
            }
            .pd-3-solid {
                padding: 3px;
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
            .pd-3-dot-center {
                padding: 3px;
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
            .pd-3-dot-left {
                padding: 3px;
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
            .pd-3-solid-left {
                padding: 3px;
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
            .pd-3-solid-center {
                padding: 3px;
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
            .pd-3-solid-top-center {
                padding: 3px;
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
            .leftMiddle{
                float: left;
                width: 50%;
            }
            .rightMiddle{
                float: right;
                width: 49%;
            }
            .left2 {
                float: left;
                width: 69%;
            }
        ";
    }
}
