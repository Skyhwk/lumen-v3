<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use App\Support\BeritaAcaraKejadianDampak;
use App\Support\BeritaAcaraKejadianDokumentasiStorage;
use App\Models\MasterKaryawan;
use App\Models\QrDocument;
use App\Support\DisiplinerPdfHeader;
use App\Support\DisiplinerPdfLayout;
use App\Support\DokumenPageBackgroundWithNote;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BeritaAcaraKejadianDocumentService
{
    private const STORAGE_DIR = 'dokumen/disipliner/berita-acara-kejadian';

    private const PAGE_BACKGROUND = 'images/dokumen-karyawan-page-background.png';

    /** Ukuran aman per panggilan WriteHTML (hindari pcre.backtrack_limit mPDF). */
    private const MPDF_HTML_CHUNK_BYTES = 400_000;

    /** @var array<int, string> */
    private array $deferredLampiranTempDirs = [];

    public function invalidateStoredPdf(BeritaAcaraKejadianDisipliner $document): void
    {
        $relativePath = trim((string) ($document->file_path ?? ''));
        if ($relativePath !== '') {
            $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        if ($document->file_path || $document->nama_berkas || $document->file_size) {
            $document->nama_berkas = null;
            $document->file_path = null;
            $document->file_size = null;
            $document->timestamps = false;
            $document->save();
            $document->timestamps = true;
        }
    }

    public function hasStoredPdf(BeritaAcaraKejadianDisipliner $document): bool
    {
        $relativePath = trim((string) ($document->file_path ?? ''));
        if ($relativePath === '') {
            return false;
        }

        $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));

        return is_file($fullPath) && filesize($fullPath) > 0;
    }

    /**
     * Selalu render ulang agar preview/PDF mengikuti data & template terbaru (tanpa baca cache disk).
     */
    public function resolvePdfBinary(BeritaAcaraKejadianDisipliner $document, object $detailRow): string
    {
        return $this->renderPdf($document, $detailRow);
    }

    /** Paksa render ulang dari blade terbaru dan timpa arsip PDF di disk. */
    public function regenerateStoredPdf(BeritaAcaraKejadianDisipliner $document, object $detailRow): BeritaAcaraKejadianDisipliner
    {
        $linkedQr = trim((string) ($document->file_qr ?? ''));
        if ($linkedQr !== '') {
            GenerateQrDocumentDisipliner::ensurePublicSvgForFile($linkedQr);
        }

        return $this->generateAndStorePdf($document, $detailRow);
    }

    public function generateAndStorePdf(BeritaAcaraKejadianDisipliner $document, object $detailRow): BeritaAcaraKejadianDisipliner
    {
        $previousRelativePath = trim((string) ($document->file_path ?? ''));

        $pdfBinary = $this->renderPdf($document, $detailRow);
        $directory = public_path(self::STORAGE_DIR . '/' . $document->karyawan_id);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder arsip berita acara kejadian gagal dibuat.');
        }

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '_', $document->nomor_surat);
        $fileName = $safeNumber . '.pdf';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($fullPath, $pdfBinary) === false) {
            throw new RuntimeException('Gagal menyimpan file PDF berita acara kejadian.');
        }

        $relativePath = self::STORAGE_DIR . '/' . $document->karyawan_id . '/' . $fileName;

        if ($previousRelativePath !== '' && $previousRelativePath !== $relativePath) {
            $oldFullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $previousRelativePath));
            if (is_file($oldFullPath)) {
                @unlink($oldFullPath);
            }
        }

        $document->nama_berkas = $fileName;
        $document->file_path = $relativePath;
        $document->file_size = filesize($fullPath) ?: null;
        $document->timestamps = false;
        $document->save();
        $document->timestamps = true;

        return $document->fresh();
    }

    public function readPdfBinary(BeritaAcaraKejadianDisipliner $document): string
    {
        if (!$this->hasStoredPdf($document)) {
            throw new RuntimeException('File berita acara kejadian belum tersedia di server.');
        }

        $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $document->file_path));
        $binary = file_get_contents($fullPath);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('File berita acara kejadian tidak dapat dibaca.');
        }

        return $binary;
    }

    public function renderPdf(BeritaAcaraKejadianDisipliner $document, object $detailRow): string
    {
        $this->deferredLampiranTempDirs = [];

        $backtrackLimit = (int) ini_get('pcre.backtrack_limit');
        if ($backtrackLimit > 0 && $backtrackLimit < 5_000_000) {
            @ini_set('pcre.backtrack_limit', '5000000');
        }

        Carbon::setLocale('id');

        $issueDate = $document->tanggal_kejadian
            ? Carbon::parse($document->tanggal_kejadian)->locale('id')
            : Carbon::now()->locale('id');

        $incidentDate = $document->tanggal_kejadian
            ? Carbon::parse($document->tanggal_kejadian)->locale('id')
            : $issueDate->copy();

        $lampiranPrepared = $this->prepareLampiranImages($document->dokumentasi ?? null, (int) $document->id);
        $lampiranImages = $lampiranPrepared['srcs'];
        $uploadedCount = $lampiranPrepared['file_count'];

        $lampiran = trim((string) ($document->lampiran ?? ''));
        if ($lampiran === '' && $uploadedCount > 0) {
            $lampiran = (string) $uploadedCount;
        }
        if ($lampiran === '') {
            $lampiran = '-';
        }

        $waktu = trim((string) ($document->waktu_kejadian ?? ''));
        $pukulKejadian = $waktu !== '' ? preg_replace('/\s*(WIB|WITA|WIT)\s*$/i', '', $waktu) : '-';

        $lokasi = trim((string) ($document->lokasi_kejadian ?? '')) ?: '-';
        $telahTerjadiKejadian = trim((string) ($document->telah_terjadi_kejadian ?? ''));
        $perihal = trim((string) ($document->perihal ?? ''));
        $uraianHtml = trim((string) ($document->uraian_kejadian ?? ''));
        $kronologiHasContent = trim(strip_tags($uraianHtml)) !== '';
        if (!$kronologiHasContent) {
            $uraianHtml = '';
        }
        $dampakAset = BeritaAcaraKejadianDampak::parse($document->dampak_aset_perusahaan);
        $showDampakAset = trim((string) ($dampakAset['nama_jenis_aset'] ?? '')) !== '';
        $dampakJenis = trim((string) ($dampakAset['jenis_kerusakan_aset'] ?? ''));
        $dampakNoAsetPlat = trim((string) ($dampakAset['no_aset_no_plat'] ?? ''));
        if ($dampakNoAsetPlat === '') {
            $dampakNoAsetPlat = '-';
        }

        $linkedQr = trim((string) ($document->file_qr ?? ''));
        if ($linkedQr !== '') {
            GenerateQrDocumentDisipliner::ensurePublicSvgForFile($linkedQr);
        }

        $qrImagePath = $this->resolveQrImageForPdf($linkedQr);
        $showLampiranSection = $lampiranImages !== [];

        $refNumber = trim((string) $document->nomor_surat);
        $headerTitle = 'BERITA ACARA KEJADIAN';
        $headerRef = 'No. : ' . $refNumber;

        $hariDanTanggal = $incidentDate->translatedFormat('l') . ', ' . $incidentDate->translatedFormat('d F Y');

        $html = view('disipliner.berita-acara-kejadian', [
            'lampiran' => $lampiran,
            'hariDanTanggal' => $hariDanTanggal,
            'pukulKejadian' => $pukulKejadian,
            'lokasiKejadian' => $lokasi,
            'telahTerjadiKejadian' => $telahTerjadiKejadian,
            'perihal' => $perihal,
            'dampakAset' => $dampakAset,
            'showDampakAset' => $showDampakAset,
            'dampakNoAsetPlat' => $dampakNoAsetPlat,
            'dampakJenisSebagian' => $dampakJenis === 'sebagian',
            'dampakJenisSeluruhnya' => $dampakJenis === 'seluruhnya',
            'dampakAsetSectionTitle' => BeritaAcaraKejadianDampak::SECTION_TITLE,
            'uraianHtml' => $kronologiHasContent ? $uraianHtml : '',
            'kronologiHasContent' => $kronologiHasContent,
            'signatoryNama' => trim((string) ($document->penandatangan_nama ?? '')) ?: '-',
            'signatoryTempatTanggalLahir' => $this->formatSignatoryTempatTanggalLahir(
                trim((string) ($document->penandatangan_tempat_lahir ?? '')),
                trim((string) ($document->penandatangan_tanggal_lahir ?? ''))
            ),
            'signatoryNomorHp' => trim((string) ($document->penandatangan_nomor_hp ?? '')) ?: '-',
            'signatoryAlamat' => trim((string) ($document->penandatangan_alamat ?? '')) ?: '-',
            'signatoryNik' => trim((string) ($document->penandatangan_nik ?? '')) ?: '-',
            'signatoryDivisi' => trim((string) ($document->penandatangan_divisi ?? '')) ?: '-',
            'signatoryPosisi' => trim((string) ($document->penandatangan_jabatan ?? '')) ?: '-',
            'issueDateLong' => $issueDate->translatedFormat('d F Y'),
            'qrImagePath' => $qrImagePath,
            'showLampiranSection' => $showLampiranSection,
            'lampiranImages' => $lampiranImages,
            'embedLampiranInMain' => false,
        ])->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];

        $pdf = new MpdfService(DisiplinerPdfLayout::mpdfPageConfig([
            'tempDir' => storage_path('app/tmp'),
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
        ]));

        $headerHtml = DisiplinerPdfHeader::build(
            DisiplinerPdfHeader::resolveLogoPath(),
            $headerTitle,
            $headerRef,
            true
        );
        $pdf->SetHTMLHeader($headerHtml);
        $pdf->SetHTMLHeader($headerHtml, 'E');

        $footerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-collapse:collapse; border-top:0.4px solid #9a9a9a;">'
            . '<tr>'
            . '<td style="width:28%; font-size:8pt; vertical-align:bottom; padding-top:2mm;">T02/R01; 07 Desember 2021</td>'
            . '<td style="width:44%; text-align:center; font-size:9.5pt; line-height:1.3; padding-top:2mm;">'
            . 'Ruko Icon Business Park blok O no. 5 – 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com'
            . '</td>'
            . '<td style="width:28%;">&nbsp;</td>'
            . '</tr></table>';

        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $backgroundSource = resource_path(self::PAGE_BACKGROUND);
        $background = $backgroundSource;
        if (is_file($backgroundSource)) {
            $background = DokumenPageBackgroundWithNote::withSystemNote(
                $backgroundSource,
                Carbon::now()->format('YmdGi')
            );
            $pdf->SetWatermarkImage($background, 1, [210, 297], [0, 0]);
            $pdf->showWatermarkImage = true;
        }

        $this->writeMpdfHtmlInChunks($pdf, $html);

        if ($showLampiranSection) {
            // Tutup halaman utama terakhir selagi watermark masih aktif, lalu matikan untuk halaman lampiran.
            $pdf->AddPage();
            $pdf->showWatermarkImage = false;
            $this->writeLampiranPages($pdf, $lampiranImages);
        }

        $output = $pdf->Output('', 'S');
        DokumenPageBackgroundWithNote::deleteIfTemporary($background, $backgroundSource);
        $this->cleanupDeferredLampiranTempDirs();

        return $output;
    }

    /**
     * @param array<int, string> $imagePaths path absolut (bukan base64)
     */
    private function writeLampiranPages(MpdfService $pdf, array $imagePaths): void
    {
        foreach ($imagePaths as $index => $imagePath) {
            if ($index > 0) {
                $pdf->AddPage();
            }

            $pageHtml = view('disipliner.berita-acara-kejadian-lampiran-page', [
                'imageSrc' => $imagePath,
                'showHeading' => $index === 0,
            ])->render();

            $pdf->WriteHTML($pageHtml);
        }
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

    private function resolveQrImageForPdf(?string $qrFile): ?string
    {
        if ($qrFile === null || $qrFile === '') {
            return null;
        }

        $qrRecord = QrDocument::query()
            ->where('file', $qrFile)
            ->where('type_document', GenerateQrDocumentDisipliner::TYPE_BERITA_ACARA_KEJADIAN)
            ->first();

        if ($qrRecord && !empty($qrRecord->kode_qr)) {
            $tempDir = storage_path('app/tmp-bak-qr');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $pngPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('bak_qr_', true) . '.png';

            try {
                QrCode::format('png')
                    ->size(120)
                    ->margin(0)
                    ->generate('https://www.intilab.com/validation/' . $qrRecord->kode_qr, $pngPath);

                if (is_file($pngPath)) {
                    return str_replace('\\', '/', $pngPath);
                }
            } catch (\Throwable $th) {
                Log::warning('BAK QR PNG gagal; coba SVG/Imagick', [
                    'file_qr' => $qrFile,
                    'message' => $th->getMessage(),
                ]);
            }
        }

        try {
            $svgPath = \App\Support\QrDocumentsStorage::svgPath($qrFile);
        } catch (\InvalidArgumentException $e) {
            Log::warning('BAK QR path tidak valid', ['file_qr' => $qrFile]);

            return null;
        }

        if (!is_file($svgPath)) {
            Log::warning('BAK QR SVG tidak ada di disk', ['path' => $svgPath]);

            return null;
        }

        $pngFromSvg = $this->convertQrSvgToPngForMpdf($svgPath);
        if ($pngFromSvg !== null) {
            return $pngFromSvg;
        }

        return str_replace('\\', '/', $svgPath);
    }

    private function convertQrSvgToPngForMpdf(string $svgPath): ?string
    {
        if (!extension_loaded('imagick') || !is_file($svgPath)) {
            return null;
        }

        $tempDir = storage_path('app/tmp-bak-qr');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pngPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('bak_qr_svg_', true) . '.png';

        try {
            $imagick = new \Imagick();
            $imagick->setBackgroundColor(new \ImagickPixel('white'));
            $imagick->readImage($svgPath);
            $imagick->setImageFormat('png');
            $imagick->writeImage($pngPath);
            $imagick->clear();

            return is_file($pngPath) ? str_replace('\\', '/', $pngPath) : null;
        } catch (\Throwable $th) {
            Log::warning('BAK konversi QR SVG→PNG gagal', [
                'svg' => $svgPath,
                'message' => $th->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Lampiran sudah JPG di disk (konversi PDF saat upload). PDF legacy dikonversi sekali saat render.
     *
     * @return array{srcs: array<int, string>, file_count: int}
     */
    private function prepareLampiranImages($raw, int $documentId): array
    {
        $items = BeritaAcaraKejadianDokumentasiStorage::decodeList($raw);
        if ($items === [] || $documentId <= 0) {
            return ['srcs' => [], 'file_count' => 0];
        }

        $srcs = [];
        $fileCount = count($items);

        foreach ($items as $storedName) {
            $full = BeritaAcaraKejadianDokumentasiStorage::fullPath($documentId, $storedName);
            if ($full === '' || !is_file($full)) {
                continue;
            }
            $normalized = str_replace('\\', '/', $full);

            if (preg_match('/\.pdf$/i', $normalized)) {
                $srcs = array_merge($srcs, $this->renderLegacyPdfLampiran($normalized));
                continue;
            }

            $src = $this->mpdfImagePath($normalized);
            if ($src !== null) {
                $srcs[] = $src;
            }
        }

        return ['srcs' => $srcs, 'file_count' => $fileCount];
    }

    /**
     * @return array<int, string> path file JPG untuk mPDF
     */
    private function renderLegacyPdfLampiran(string $pdfPath): array
    {
        Log::warning('Lampiran PDF lama ditemukan — konversi Imagick saat render. Simpan ulang dokumen untuk konversi permanen.', [
            'path' => $pdfPath,
        ]);

        $tempDir = storage_path('app/tmp-bak-lampiran/' . uniqid('legacy_', true));
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            return [];
        }
        try {
            $converter = app(BeritaAcaraKejadianLampiranConvertService::class);
            $jpegPaths = $converter->convertPdfToJpegFiles($pdfPath, $tempDir, 'legacy');
            $srcs = [];
            foreach ($jpegPaths as $jpegPath) {
                $src = $this->mpdfImagePath($jpegPath);
                if ($src !== null) {
                    $srcs[] = $src;
                }
            }

            if ($srcs !== []) {
                $this->deferredLampiranTempDirs[] = $tempDir;
            } else {
                $this->removeLampiranTempDir($tempDir);
            }

            return $srcs;
        } catch (\Throwable $ex) {
            Log::warning('Konversi lampiran PDF legacy gagal', ['path' => $pdfPath, 'message' => $ex->getMessage()]);
            $this->removeLampiranTempDir($tempDir);

            return [];
        }
    }

    /** Path absolut dengan slash mPDF (tanpa base64 — HTML tetap kecil). */
    private function mpdfImagePath(string $path): ?string
    {
        if (!is_file($path) || filesize($path) <= 0) {
            return null;
        }

        return str_replace('\\', '/', $path);
    }

    private function cleanupDeferredLampiranTempDirs(): void
    {
        foreach ($this->deferredLampiranTempDirs as $tempDir) {
            $this->removeLampiranTempDir($tempDir);
        }

        $this->deferredLampiranTempDirs = [];
    }

    private function removeLampiranTempDir(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }

        foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($tempDir);
    }

    private function formatSignatoryTempatTanggalLahir(string $tempat, string $tanggal): string
    {
        if ($tempat !== '' && $tempat !== '-' && $tanggal !== '' && $tanggal !== '-') {
            return $tempat . ', ' . $tanggal;
        }
        if ($tempat !== '' && $tempat !== '-') {
            return $tempat;
        }
        if ($tanggal !== '' && $tanggal !== '-') {
            return $tanggal;
        }

        return '-';
    }
}
