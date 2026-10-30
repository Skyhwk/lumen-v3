<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\QrDocument;
use App\Models\SuratTeguranDisipliner;
use App\Support\DisiplinerHonorific;
use App\Support\DisiplinerPdfHeader;
use App\Support\DisiplinerPdfLayout;
use App\Support\DokumenPageBackgroundWithNote;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SuratTeguranDocumentService
{
    private const STORAGE_DIR = 'dokumen/disipliner/surat-teguran';

    private const PAGE_BACKGROUND = 'images/dokumen-karyawan-page-background.png';

    public function hasStoredPdf(SuratTeguranDisipliner $letter): bool
    {
        $relativePath = trim((string) ($letter->file_path ?? ''));
        if ($relativePath === '') {
            return false;
        }

        $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));

        return is_file($fullPath) && filesize($fullPath) > 0;
    }

    public function invalidateStoredPdf(SuratTeguranDisipliner $letter): void
    {
        $relativePath = trim((string) ($letter->file_path ?? ''));
        if ($relativePath !== '') {
            $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        if ($letter->file_path || $letter->nama_berkas || $letter->file_size) {
            $letter->nama_berkas = null;
            $letter->file_path = null;
            $letter->file_size = null;
            $letter->timestamps = false;
            $letter->save();
            $letter->timestamps = true;
        }
    }

    public function resolvePdfBinary(SuratTeguranDisipliner $letter, object $detailRow): string
    {
        if ($this->hasStoredPdf($letter)) {
            return $this->readPdfBinary($letter);
        }

        $this->generateAndStorePdf($letter, $detailRow);

        return $this->readPdfBinary($letter->fresh());
    }

    public function regenerateStoredPdf(SuratTeguranDisipliner $letter, object $detailRow): SuratTeguranDisipliner
    {
        $linkedQr = trim((string) ($letter->file_qr ?? ''));
        if ($linkedQr !== '') {
            GenerateQrDocumentDisipliner::ensurePublicSvgForFile($linkedQr);
        }

        return $this->generateAndStorePdf($letter, $detailRow);
    }

    public function generateAndStorePdf(SuratTeguranDisipliner $letter, object $detailRow): SuratTeguranDisipliner
    {
        $previousRelativePath = trim((string) ($letter->file_path ?? ''));

        $pdfBinary = $this->renderPdf($letter, $detailRow);
        $directory = public_path(self::STORAGE_DIR . '/' . $letter->karyawan_id);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder arsip surat teguran gagal dibuat.');
        }

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '_', $letter->nomor_surat);
        $fileName = $safeNumber . '.pdf';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($fullPath, $pdfBinary) === false) {
            throw new RuntimeException('Gagal menyimpan file PDF surat teguran.');
        }

        $relativePath = self::STORAGE_DIR . '/' . $letter->karyawan_id . '/' . $fileName;

        if ($previousRelativePath !== '' && $previousRelativePath !== $relativePath) {
            $oldFullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $previousRelativePath));
            if (is_file($oldFullPath)) {
                @unlink($oldFullPath);
            }
        }

        $letter->nama_berkas = $fileName;
        $letter->file_path = $relativePath;
        $letter->file_size = filesize($fullPath) ?: null;
        $letter->timestamps = false;
        $letter->save();
        $letter->timestamps = true;

        return $letter->fresh();
    }

    public function readPdfBinary(SuratTeguranDisipliner $letter): string
    {
        if (!$this->hasStoredPdf($letter)) {
            throw new RuntimeException('File surat teguran belum tersedia di server.');
        }

        $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $letter->file_path));
        $binary = file_get_contents($fullPath);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('File surat teguran tidak dapat dibaca.');
        }

        return $binary;
    }

    public function renderPdf(SuratTeguranDisipliner $letter, object $detailRow): string
    {
        Carbon::setLocale('id');

        $issueDate = $this->resolveSignatureDate($letter);

        $employeeRow = MasterKaryawan::query()
            ->with('cabang')
            ->find($letter->karyawan_id);

        $honorific = DisiplinerHonorific::fromJenisKelamin($employeeRow ? $employeeRow->jenis_kelamin : null);
        $placement = '-';
        if ($employeeRow && $employeeRow->cabang) {
            $placement = trim((string) $employeeRow->cabang->nama_cabang) ?: '-';
        }

        $namaRaw = trim((string) ($detailRow->nama_lengkap ?? ''));
        $nama = $namaRaw !== '' ? Str::title(mb_strtolower($namaRaw, 'UTF-8')) : '-';
        $nik = trim((string) ($detailRow->nik_karyawan ?? ''));
        $employeeNameNik = $nik !== '' ? $nama . ' / ' . $nik : $nama;

        $divisi = trim((string) ($detailRow->nama_divisi ?? ''));
        if ($divisi === '') {
            $divisi = trim((string) ($detailRow->department ?? '')) ?: '-';
        }
        $posisi = trim((string) ($detailRow->department ?? ''));
        $divisionName = ($posisi !== '' && $posisi !== $divisi)
            ? $divisi . ' / ' . $posisi
            : $divisi;

        $positionName = trim((string) ($detailRow->jabatan ?? '')) ?: '-';
        $perihal = trim((string) $letter->perihal) ?: '-';
        $tanggalKejadian = $this->formatDateOnly($letter->tanggal_kejadian);
        $violationHtml = $this->buildViolationHtml(
            (string) $letter->uraian_pelanggaran,
            $tanggalKejadian,
            $honorific
        );

        $refNumber = trim((string) $letter->nomor_surat);
        $headerTitle = 'SURAT TEGURAN';
        $headerRef = 'No. : ' . $refNumber;

        $qrImagePath = $this->resolveQrImageForPdf(trim((string) ($letter->file_qr ?? '')));

        $signatoryName = trim((string) ($letter->nama_penandatangan ?? ''));
        $signatoryTitle = trim((string) ($letter->jabatan_penandatangan ?? ''));

        $html = view('disipliner.surat-teguran', [
            'perihal' => $perihal,
            'employeeNameNik' => $employeeNameNik,
            'divisionName' => $divisionName,
            'positionName' => $positionName,
            'placementName' => $placement,
            'violationHtml' => $violationHtml,
            'honorific' => $honorific,
            'issueDateLong' => $issueDate->translatedFormat('d F Y'),
            'qrImagePath' => $qrImagePath,
            'signatoryName' => $signatoryName,
            'signatoryTitle' => $signatoryTitle,
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
            . '<tr><td style="text-align:center; font-size:9.5pt; line-height:1.3; padding-top:2mm;">'
            . 'Ruko Icon Business Park blok O no. 5 – 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com'
            . '</td></tr></table>';

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

    /**
     * Poin 1 di PDF diawali "Pada tanggal {tanggal kejadian}, {Saudara/Saudari} …"
     * jika tanggal kejadian diisi dan poin 1 belum memuat kalimat tersebut.
     */
    private function buildViolationHtml(string $html, ?string $tanggalKejadian, string $honorific): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $items = $this->extractViolationItems($html);
        if ($items === []) {
            return '<p>-</p>';
        }

        $tanggalKejadian = trim((string) $tanggalKejadian);
        if ($tanggalKejadian !== '' && !preg_match('/^Pada tanggal\s/i', $items[0])) {
            $dateLong = $this->parseDateOnlyCarbon($tanggalKejadian)->locale('id')->translatedFormat('d F Y');
            $first = preg_replace('/^-\s*/', '', $items[0]);
            $first = preg_replace('/^(Saudara|Saudari)\s+/iu', '', trim($first));
            $items[0] = "Pada tanggal {$dateLong}, {$honorific} {$first}";
        }

        $out = '';
        foreach ($items as $line) {
            $line = preg_replace('/^-\s*/', '', trim($line));
            $out .= '<p>- ' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return $out;
    }

    /** @return list<string> */
    private function extractViolationItems(string $html): array
    {
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches) && !empty($matches[1])) {
            $items = [];
            foreach ($matches[1] as $chunk) {
                $text = trim(html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($text !== '') {
                    $items[] = $text;
                }
            }

            if ($items !== []) {
                return $items;
            }
        }

        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches) && !empty($matches[1])) {
            $items = [];
            foreach ($matches[1] as $chunk) {
                $text = trim(html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($text !== '') {
                    $items[] = $text;
                }
            }

            if ($items !== []) {
                return $items;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', strip_tags($html));
        $items = array_values(array_filter(array_map('trim', $lines), static fn ($line) => $line !== ''));

        return $items;
    }

    /** Tanggal di blok tanda tangan: hari pengesahan (terbit) atau hari ini untuk draf. */
    private function resolveSignatureDate(SuratTeguranDisipliner $letter): Carbon
    {
        $tz = 'Asia/Jakarta';

        if (
            $letter->status === SuratTeguranDisipliner::STATUS_ISSUED
            && !empty($letter->updated_at)
        ) {
            return Carbon::parse($letter->updated_at)->timezone($tz)->locale('id');
        }

        return Carbon::now($tz)->locale('id');
    }

    private function formatDateOnly($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->timezone('Asia/Jakarta')->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }

        return Carbon::parse($raw)->timezone('Asia/Jakarta')->format('Y-m-d');
    }

    private function parseDateOnlyCarbon(string $ymd): Carbon
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $ymd, $m)) {
            $ymd = $m[1];
        }

        return Carbon::createFromFormat('Y-m-d', $ymd, 'Asia/Jakarta')->startOfDay();
    }

    private function resolveQrImageForPdf(?string $qrFile): ?string
    {
        if ($qrFile === null || $qrFile === '') {
            return null;
        }

        $qrRecord = QrDocument::query()
            ->where('file', $qrFile)
            ->where('type_document', GenerateQrDocumentDisipliner::TYPE_SURAT_TEGURAN)
            ->first();

        if ($qrRecord && !empty($qrRecord->kode_qr)) {
            $tempDir = storage_path('app/tmp-st-qr');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $pngPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('st_qr_', true) . '.png';

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

}
