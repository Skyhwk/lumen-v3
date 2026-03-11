<?php

namespace App\Services;

use Illuminate\Support\Facades\View;

use App\Services\MpdfService as PDF;
use Mpdf\HTMLParserMode;

class OrderChangeNotifier
{
    public function notify($orders)
    {
        $pdf = new PDF();
        $html = View::make('order-changes', compact('orders'))->render();

        $footer = array(
            'odd' => array(
                'L' => array(
                    'content' => '',
                    'font-size' => 4,
                    'font-style' => 'I',
                    // 'font-style' => 'B',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'C' => array(
                    'content' => 'Dokumen ini diterbitkan otomatis oleh sistem <br> ',
                    'font-size' => 5,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'R' => array(
                    'content' => 'Print: {DATE YmdGi}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    // 'font-style' => 'B',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'line' => -1,
            )
        );

        $pdf->setFooter($footer);
        $pdf->setDisplayMode('fullpage');
        $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png');
        $pdf->showWatermarkImage = true;
        $pdf->watermarkImageAlpha = 0.1;

        $stylesheet = '
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                color: #333;
            }
            .header-section {
                display: flex;
                align-items: center;
            }
            .subtitle {
                margin-top: 8px;
            }
            .logo {
                height: 60px;
                margin-right: 20px;
            }
            .title-text {
                font-size: 18px;
                font-weight: bold;
                margin-top: 20px;
            }
            .section-title {
                font-size: 14px;
                font-weight: bold;
                margin-top: 30px;
                margin-bottom: 10px;
                border-bottom: 1px solid #ccc;
                padding-bottom: 4px;
            }
            .info-table, .detail-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }
            .info-table td {
                padding: 4px 8px;
            }
            .detail-table th, .detail-table td {
                border: 1px solid #ccc;
                padding: 6px 8px;
                text-align: left;
            }
            .detail-table th {
                background-color: #f5f5f5;
            }
            .status-add {
                background-color: #d4edda;
            }
            .status-sub {
                background-color: #f8d7da;
            }
            .footer {
                margin-top: 40px;
                font-size: 12px;
            }
        ';
        $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        return $pdf->Output('', 'I');
    }
}
