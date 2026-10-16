<?php

namespace App\Services\GaAsset;

use App\Models\GaAsset;
use Carbon\Carbon;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Mpdf;

class GaAssetQrPdfService
{
    private const STICKERS_PER_ROW = 6;
    private const ROWS_PER_PAGE = 5;

    public function export(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw new \InvalidArgumentException('Pilih minimal satu aset untuk export QR.');
        }

        $records = collect();
        foreach (array_chunk($ids, 1000) as $chunk) {
            $records = $records->concat(
                GaAsset::query()
                    ->where('is_active', 1)
                    ->whereIn('id', $chunk)
                    ->whereNotNull('cs_code')
                    ->where('cs_code', '!=', '')
                    ->get(['id', 'cs_code', 'name'])
            );
        }
        $assets = $records->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if ($assets->has($id)) {
                $ordered[] = $assets->get($id);
            }
        }
        if ($ordered === []) {
            throw new \InvalidArgumentException('Aset terpilih tidak memiliki NO CS yang valid.');
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A3',
            'margin_left' => 15,
            'margin_right' => 5,
            'margin_top' => 15,
            'margin_header' => 0,
            'margin_bottom' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->WriteHTML('<!DOCTYPE html>
            <html>
            <head>
                <style>
                    .box {
                        padding: 2px;
                        border: 1px solid black;
                        text-align: center;
                        position: relative;
                    }
                    .text-header {
                        font-size: 8px;
                    }
                    .sticker {
                        width: 12%;
                        height: auto;
                    }
                    .cs-code {
                        font-size: 6.5px;
                        font-weight: 700;
                        letter-spacing: 0.02em;
                        word-wrap: break-word;
                    }
                </style>
            </head>
            <body>
                <table border="0" cellspacing="15" cellpadding="0" width="0">
                    <tr>');

        $count = 0;
        $total = count($ordered);
        $perPage = self::STICKERS_PER_ROW * self::ROWS_PER_PAGE;
        $tableOpen = '<table border="0" cellspacing="15" cellpadding="0" width="0"><tr>';
        $writer = new PngWriter();
        $logo = $this->logo();
        $qrDir = public_path('barcode/aset');
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0777, true);
        }

        foreach ($ordered as $asset) {
            $count++;
            $csCode = trim((string) $asset->cs_code);
            $pngPath = $qrDir . '/GA-' . $asset->id . '.png';
            if (!is_file($pngPath)) {
                $qr = QrCode::create($csCode)
                    ->setEncoding(new Encoding('UTF-8'))
                    ->setSize(250)
                    ->setMargin(15)
                    ->setForegroundColor(new Color(0, 0, 0))
                    ->setBackgroundColor(new Color(255, 255, 255));
                if ($logo) {
                    $qr->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
                }
                $writer->write($qr, $logo)->saveToFile($pngPath);
            }
            $safeCs = htmlspecialchars($csCode, ENT_QUOTES, 'UTF-8');

            $mpdf->WriteHTML('<td class="box">
                    <div>
                        <span class="text-header">PT INTI SURYA LABORATORIUM</span>
                        <br>
                        <span class="text-header">Inventaris Aset</span>
                        <br>
                        <img class="sticker" src="' . $pngPath . '">
                        <div class="cs-code">' . $safeCs . '</div>
                    </div>
                </td>');

            if ($count % self::STICKERS_PER_ROW === 0) {
                if ($count % $perPage === 0 && $count < $total) {
                    $mpdf->WriteHTML('</tr></table>');
                    $mpdf->AddPage();
                    $mpdf->WriteHTML($tableOpen);
                } else {
                    $mpdf->WriteHTML('</tr><tr>');
                }
            }
        }

        $mpdf->WriteHTML('</tr></table></body></html>');

        $dir = public_path('dokumen/qr_aset');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'QR_Aset_GA_' . str_replace('.', '_', (string) microtime(true)) . '.pdf';
        $mpdf->Output($dir . '/' . $filename, \Mpdf\Output\Destination::FILE);

        $exportedIds = array_map(function ($asset) {
            return (int) $asset->id;
        }, $ordered);
        $this->markLabeled($exportedIds);

        return [
            'filename' => $filename,
            'url' => rtrim((string) env('APP_URL'), '/') . '/public/dokumen/qr_aset/' . $filename,
            'count' => count($ordered),
        ];
    }

    private function markLabeled(array $ids): void
    {
        $now = Carbon::now();
        foreach (array_chunk($ids, 1000) as $chunk) {
            GaAsset::query()
                ->whereIn('id', $chunk)
                ->update([
                    'label_legacy' => 1,
                    'updated_at' => $now,
                ]);
        }
    }

    private function logo(): ?Logo
    {
        $logoPath = public_path('barcode/emisi/logo-final.png');
        if (!file_exists($logoPath)) {
            return null;
        }

        return Logo::create($logoPath)->setResizeToWidth(50);
    }
}
