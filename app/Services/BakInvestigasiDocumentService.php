<?php

namespace App\Services;

use App\Support\InvestigasiAwalStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mpdf\Output\Destination;
use RuntimeException;

final class BakInvestigasiDocumentService
{
    private const STORAGE_DIR = 'dokumen/disipliner/investigasi-awal';

    private const MPDF_HTML_CHUNK_BYTES = 400_000;

    public function issuePdf(int $investigasiId): array
    {
        $row = DB::table('bak_investigasi')->where('id', $investigasiId)->first();
        if ($row === null) {
            throw new RuntimeException('Investigasi tidak ditemukan.');
        }

        if (($row->status ?? '') !== InvestigasiAwalStatus::ISSUED) {
            throw new RuntimeException('Investigasi belum berstatus Terbit.');
        }

        $binary = $this->renderPdfBinary($investigasiId);

        $noDoc = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($row->no_document ?? 'f1'));
        $fileName = 'F1-' . $noDoc . '.pdf';
        $relativeDir = self::STORAGE_DIR . '/' . date('Y/m');
        $absoluteDir = storage_path('app/public/' . $relativeDir);
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $absolutePath = $absoluteDir . '/' . $fileName;
        file_put_contents($absolutePath, $binary);

        $publicPath = $relativeDir . '/' . $fileName;
        DB::table('bak_investigasi')
            ->where('id', $investigasiId)
            ->update([
                'nama_berkas' => $fileName,
                'file_path' => $publicPath,
                'file_size' => is_file($absolutePath) ? filesize($absolutePath) : null,
                'updated_at' => Carbon::now(),
            ]);

        return [
            'file_name' => $fileName,
            'file_path' => $publicPath,
            'binary' => $binary,
        ];
    }

    public function printPdfBinary(int $investigasiId): string
    {
        return $this->renderPdfBinary($investigasiId);
    }

    private function renderPdfBinary(int $investigasiId): string
    {
        $viewData = InvestigasiAwalPdfPresenter::build($investigasiId);
        $html = view('disipliner.investigasi-awal', $viewData)->render();
        $headerHtml = view('disipliner.investigasi-awal-header', $viewData)->render();
        $footerHtml = view('disipliner.investigasi-awal-footer', $viewData)->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];

        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'Letter',
            'tempDir' => storage_path('app/tmp'),
            'margin_left' => 23,
            'margin_right' => 23,
            'margin_top' => 30,
            'margin_bottom' => 25,
            'margin_header' => 6,
            'margin_footer' => 7,
            'fontDir' => array_merge($fontDirs, [resource_path('fonts/Calibri')]),
            'fontdata' => $fontData + [
                'calibri' => [
                    'R' => 'calibri.ttf',
                    'B' => 'calibrib.ttf',
                    'I' => 'calibrii.ttf',
                    'BI' => 'calibriz.ttf',
                ],
            ],
            'default_font' => 'calibri',
        ]);

        $pdf->SetHTMLHeader($headerHtml);
        $pdf->SetHTMLHeader($headerHtml, 'E');
        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $this->writeMpdfHtmlInChunks($pdf, $html);

        return $pdf->Output('', Destination::STRING_RETURN);
    }

    private function writeMpdfHtmlInChunks(MpdfService $pdf, string $html): void
    {
        if (strlen($html) <= self::MPDF_HTML_CHUNK_BYTES) {
            $pdf->WriteHTML($html);

            return;
        }

        $offset = 0;
        $length = strlen($html);

        while ($offset < $length) {
            $pdf->WriteHTML(substr($html, $offset, self::MPDF_HTML_CHUNK_BYTES));
            $offset += self::MPDF_HTML_CHUNK_BYTES;
        }
    }
}
