<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use App\Support\BeritaAcaraKejadianDampak;
use App\Models\MasterKaryawan;
use App\Models\QrDocument;
use App\Support\DisiplinerPdfHeader;
use App\Support\DisiplinerPdfLayout;
use App\Support\DokumenPageBackgroundWithNote;
use Carbon\Carbon;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BeritaAcaraKejadianDocumentService
{
    private const STORAGE_DIR = 'dokumen/disipliner/berita-acara-kejadian';

    private const PAGE_BACKGROUND = 'images/dokumen-karyawan-page-background.png';

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
     * Ambil PDF dari arsip disk jika ada; jika tidak, render lalu simpan.
     */
    public function resolvePdfBinary(BeritaAcaraKejadianDisipliner $document, object $detailRow): string
    {
        if ($this->hasStoredPdf($document)) {
            return $this->readPdfBinary($document);
        }

        $this->generateAndStorePdf($document, $detailRow);

        return $this->readPdfBinary($document->fresh());
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
        Carbon::setLocale('id');

        $issueDate = $document->tanggal_kejadian
            ? Carbon::parse($document->tanggal_kejadian)->locale('id')
            : Carbon::now()->locale('id');

        $incidentDate = $document->tanggal_kejadian
            ? Carbon::parse($document->tanggal_kejadian)->locale('id')
            : $issueDate->copy();

        $lampiran = trim((string) ($document->lampiran ?? ''));
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
        $dampakJenis = trim((string) ($dampakAset['jenis_kerusakan_aset'] ?? ''));
        $dampakNoAsetPlat = trim((string) ($dampakAset['no_aset_no_plat'] ?? ''));
        if ($dampakNoAsetPlat === '') {
            $dampakNoAsetPlat = '-';
        }

        $qrImagePath = $this->resolveQrImageForPdf(trim((string) ($document->file_qr ?? '')));

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
        ])->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];

        $pdf = new MpdfService(DisiplinerPdfLayout::mpdfPageConfig([
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

        $pdf->WriteHTML($html);

        $output = $pdf->Output('', 'S');
        DokumenPageBackgroundWithNote::deleteIfTemporary($background, $backgroundSource);

        return $output;
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
                // fallback SVG
            }
        }

        try {
            $svgPath = \App\Support\QrDocumentsStorage::svgPath($qrFile);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return is_file($svgPath) ? str_replace('\\', '/', $svgPath) : null;
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
