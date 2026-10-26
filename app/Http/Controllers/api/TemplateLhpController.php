<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Tcpdf\Fpdi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TemplateLhpController extends Controller
{
    private const DRAW_ORDER = ['watermark', 'cover', 'logo', 'qr'];
    private const MAX_PDF_COUNT = 20;

    public function download(Request $request)
    {
        @set_time_limit(180);

        $qrFileName = basename((string) $request->input('qr_file', ''));
        $pdfFiles = $this->collectPdfFiles($request);
        $qrPath = $this->resolveGeneratedQrPath($qrFileName);

        if (!$pdfFiles || !$qrPath) {
            return response()->json(['message' => 'File PDF dan QR wajib tersedia'], 400);
        }

        if (count($pdfFiles) > self::MAX_PDF_COUNT) {
            return response()->json(['message' => 'Maksimal ' . self::MAX_PDF_COUNT . ' file PDF'], 400);
        }

        $overlays = json_decode($request->input('overlays', '[]'), true);
        $overlaySets = $this->syncSharedWatermark(
            $this->overlaySetsForDocuments($overlays, count($pdfFiles))
        );

        $logoPath = public_path('isl_logo.png');
        $watermarkPath = public_path('logo-watermark.png');

        try {
            $pdf = $this->makePdf();

            foreach ($pdfFiles as $index => $pdfFile) {
                $this->stampDocument(
                    $pdf,
                    $pdfFile->getRealPath(),
                    $this->sortOverlays($overlaySets[$index] ?? []),
                    $logoPath,
                    $watermarkPath,
                    $qrPath
                );
            }

            $filename = count($pdfFiles) === 1
                ? (pathinfo($pdfFiles[0]->getClientOriginalName(), PATHINFO_FILENAME) ?: 'lhp') . '-custom.pdf'
                : 'lhp-gabungan-custom.pdf';
            $content = $pdf->Output($filename, 'S');

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal membuat PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function generateQr(Request $request)
    {
        DB::beginTransaction();
        try {
            $order_detail = OrderDetail::where('cfr', $request->no_lhp)->where('is_active', 1)->first();
            if (!$order_detail) {
                DB::rollback();
                return response()->json([
                    'message' => "Data order dengan CFR $request->no_lhp tidak ditemukan"
                ], 404);
            }
            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                ->orderByDesc('berlaku_mulai')
                ->first();
            $filename = 'LHP_' . str_replace("/", "_", $order_detail->cfr);
            $directory = public_path() . "/qr_documents/";
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $path = $directory . $filename . '.svg';
            if (!file_exists($path)) {
                $link = 'https://www.intilab.com/validation/';
                $unique = 'isldc' . (int) floor(microtime(true) * 1000);

                QrCode::size(200)->generate($link . $unique, $path);
                $dataQr = [
                    'type_document' => $request->tipe_dokumen,
                    'kode_qr' => $unique,
                    'file' => $filename,
                    'data' => json_encode([
                        'Nomor_LHP' => $order_detail->cfr,
                        'Nama_Pelanggan' => $order_detail->nama_perusahaan,
                        'Pelanggan_ID' => substr($order_detail->no_order, 0, 6),
                        'Tanggal_Pengesahan' => Carbon::parse($request->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                        'Disahkan_Oleh' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                        'Jabatan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor'
                    ]),
                    'created_at' => Carbon::now(),
                    'created_by' => 'System Manual',
                ];
                QrDocument::create($dataQr);

                DB::commit();
                return response()->json([
                    'message' => "QR Document $filename berhasil digenerate",
                    'file' => $filename,
                ], 200);
            }

            DB::rollback();
            return response()->json([
                'message' => "QR Document $filename sudah ada, digunakan kembali",
                'file' => $filename,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }

    private function collectPdfFiles(Request $request)
    {
        $allFiles = $request->allFiles();
        $files = $allFiles['pdfs'] ?? $request->file('pdfs');

        if (!$files) {
            $single = $allFiles['pdf'] ?? $request->file('pdf');
            $files = $single ? [$single] : [];
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        return array_values(array_filter($files));
    }

    private function resolveGeneratedQrPath($qrFileName)
    {
        if (!$qrFileName || !preg_match('/^[A-Za-z0-9_\-]+$/', $qrFileName)) {
            return null;
        }

        $qrPath = public_path('qr_documents/' . $qrFileName . '.svg');
        return is_file($qrPath) ? $qrPath : null;
    }

    private function overlaySetsForDocuments($overlays, $documentCount)
    {
        $empty = array_fill(0, max(0, (int) $documentCount), []);
        if (!is_array($overlays) || $documentCount < 1) {
            return $empty;
        }

        $first = reset($overlays);
        $isPerDocument = is_array($first) && !array_key_exists('type', $first);

        if (!$isPerDocument) {
            $shared = array_values($overlays);
            return array_fill(0, $documentCount, $shared);
        }

        $sets = [];
        for ($index = 0; $index < $documentCount; $index++) {
            $set = $overlays[$index] ?? [];
            $sets[] = is_array($set) ? array_values($set) : [];
        }

        return $sets;
    }

    private function syncSharedWatermark(array $overlaySets)
    {
        $watermark = null;
        foreach ($overlaySets as $set) {
            foreach ($set as $overlay) {
                if (($overlay['type'] ?? '') === 'watermark') {
                    $watermark = $overlay;
                    break 2;
                }
            }
        }

        if (!$watermark) {
            return $overlaySets;
        }

        $watermark['pages'] = 'all';
        $watermark['visible'] = $watermark['visible'] ?? true;

        return array_map(function ($set) use ($watermark) {
            $without = array_values(array_filter($set, function ($overlay) {
                return ($overlay['type'] ?? '') !== 'watermark';
            }));
            $without[] = $watermark;
            return $without;
        }, $overlaySets);
    }

    private function makePdf()
    {
        $pdf = new class('P', 'mm') extends Fpdi {
            public function Header() {}
            public function Footer() {}
        };
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->SetAutoPageBreak(false, 0);

        return $pdf;
    }

    private function stampDocument(Fpdi $pdf, $sourcePath, array $sorted, $logoPath, $watermarkPath, $qrPath)
    {
        $normalizedPath = $this->normalizePdfForFpdi($sourcePath);

        try {
            $pageCount = $pdf->setSourceFile($normalizedPath);

            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $orientation = $size['orientation'] ?? ($size['width'] > $size['height'] ? 'L' : 'P');
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($template, 0, 0, $size['width'], $size['height']);

                foreach ($sorted as $overlay) {
                    if (!$this->isVisibleOnPage($overlay, $page, $pageCount)) {
                        continue;
                    }
                    $this->drawOverlay($pdf, $overlay, $size, $logoPath, $watermarkPath, $qrPath);
                }
            }
        } finally {
            if ($normalizedPath && is_file($normalizedPath)) {
                @unlink($normalizedPath);
            }
        }
    }

    private function normalizePdfForFpdi($sourcePath)
    {
        $outputPath = sys_get_temp_dir() . '/lhp_fpdi_' . uniqid('', true) . '.pdf';
        $gs = is_executable('/usr/bin/gs') ? '/usr/bin/gs' : 'gs';
        $command = sprintf(
            '%s -q -dNOPAUSE -dBATCH -dSAFER -dAutoRotatePages=/None -dUseCropBox -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=%s %s 2>&1',
            escapeshellcmd($gs),
            escapeshellarg($outputPath),
            escapeshellarg($sourcePath)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \RuntimeException(
                'PDF terenkripsi atau tidak bisa diproses. ' . trim(implode(' ', $output))
            );
        }

        return $outputPath;
    }

    private function sortOverlays(array $overlays)
    {
        usort($overlays, function ($a, $b) {
            $orderA = array_search($a['type'] ?? '', self::DRAW_ORDER, true);
            $orderB = array_search($b['type'] ?? '', self::DRAW_ORDER, true);
            $orderA = $orderA === false ? 99 : $orderA;
            $orderB = $orderB === false ? 99 : $orderB;

            return $orderA <=> $orderB;
        });

        return $overlays;
    }

    private function isVisibleOnPage(array $overlay, $page, $pageCount)
    {
        if (isset($overlay['visible']) && !$overlay['visible']) {
            return false;
        }

        $pages = $overlay['pages'] ?? 'all';
        if ($pages === 'first') {
            return (int) $page === 1;
        }
        if ($pages === 'last') {
            return (int) $page === (int) $pageCount;
        }
        if ($pages === 'page') {
            return (int) $page === (int) ($overlay['page'] ?? 0);
        }

        return true;
    }

    private function drawOverlay(Fpdi $pdf, array $overlay, array $size, $logoPath, $watermarkPath, $qrPath)
    {
        $x = $size['width'] * ((float) ($overlay['xPercent'] ?? 0) / 100);
        $y = $size['height'] * ((float) ($overlay['yPercent'] ?? 0) / 100);
        $w = $size['width'] * ((float) ($overlay['wPercent'] ?? 0) / 100);
        $h = $size['height'] * ((float) ($overlay['hPercent'] ?? 0) / 100);
        $opacity = isset($overlay['opacity']) ? (float) $overlay['opacity'] : 1;
        $type = $overlay['type'] ?? '';

        if ($w <= 0 || $h <= 0) {
            return;
        }

        if ($type === 'cover') {
            $pdf->SetAlpha(1);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x, $y, $w, $h, 'F');
            return;
        }

        $path = null;
        $imageType = '';
        if ($type === 'logo' && is_file($logoPath)) {
            $path = $logoPath;
            $imageType = 'PNG';
        } elseif ($type === 'watermark' && is_file($watermarkPath)) {
            $path = $watermarkPath;
            $imageType = 'PNG';
        } elseif ($type === 'qr' && is_file($qrPath)) {
            $pdf->SetAlpha(max(0.05, min(1, $opacity)));
            if (strtolower(pathinfo($qrPath, PATHINFO_EXTENSION)) === 'svg') {
                $pdf->ImageSVG($qrPath, $x, $y, $w, $h);
            } else {
                $pdf->Image($qrPath, $x, $y, $w, $h, 'PNG', '', '', false, 300, '', false, false, 0, 'CM');
            }
            $pdf->SetAlpha(1);
            return;
        }

        if (!$path) {
            return;
        }

        $pdf->SetAlpha(max(0.05, min(1, $opacity)));
        $pdf->Image($path, $x, $y, $w, $h, $imageType, '', '', false, 300, '', false, false, 0, 'CM');
        $pdf->SetAlpha(1);
    }
}
