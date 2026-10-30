<?php

namespace App\Services;

use App\Models\EmployeeDocumentSequence;
use App\Models\MasterKaryawan;
use App\Models\QrDocument;
use App\Models\SuratPeringatanDisipliner;
use App\Support\DisiplinerHonorific;
use App\Support\DokumenPageBackgroundWithNote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SuratPeringatanDocumentService
{
    private const STORAGE_DIR = 'dokumen/disipliner/surat-peringatan';

    private const PAGE_BACKGROUND = 'images/dokumen-karyawan-page-background.png';

    public const VALIDITY_MONTHS = [
        SuratPeringatanDisipliner::TYPE_SP1 => 6,
        SuratPeringatanDisipliner::TYPE_SP2 => 9,
        SuratPeringatanDisipliner::TYPE_SP3 => 12,
    ];

    public static function defaultMasaBerlakuBulan(string $jenisPeringatan): int
    {
        $type = strtoupper(trim($jenisPeringatan));

        return self::VALIDITY_MONTHS[$type] ?? self::VALIDITY_MONTHS[SuratPeringatanDisipliner::TYPE_SP1];
    }

    public static function defaultBentukSanksi(string $jenisPeringatan): string
    {
        $type = strtoupper(trim($jenisPeringatan));
        $map = [
            SuratPeringatanDisipliner::TYPE_SP1 => 'Surat Peringatan 1 (SP-1) dan evaluasi kinerja',
            SuratPeringatanDisipliner::TYPE_SP2 => 'Surat Peringatan 2 (SP-2) dan tindakan disiplin lanjutan',
            SuratPeringatanDisipliner::TYPE_SP3 => 'Surat Peringatan 3 (SP-3) dan tindakan disiplin sesuai ketentuan perusahaan',
        ];

        return $map[$type] ?? $map[SuratPeringatanDisipliner::TYPE_SP1];
    }

    private const TYPE_PREFIX = [
        SuratPeringatanDisipliner::TYPE_SP1 => 'ISL/SP1/',
        SuratPeringatanDisipliner::TYPE_SP2 => 'ISL/SP2/',
        SuratPeringatanDisipliner::TYPE_SP3 => 'ISL/SP3/',
    ];

    public function assertCanIssue(string $jenisPeringatan, int $karyawanId, ?int $excludeId = null): void
    {
        $jenisPeringatan = strtoupper(trim($jenisPeringatan));

        if ($jenisPeringatan === SuratPeringatanDisipliner::TYPE_SP1) {
            return;
        }

        if ($jenisPeringatan === SuratPeringatanDisipliner::TYPE_SP2) {
            if (!$this->hasIssuedType($karyawanId, SuratPeringatanDisipliner::TYPE_SP1, $excludeId)) {
                throw new RuntimeException('SP2 hanya dapat diterbitkan jika karyawan sudah memiliki SP1 yang aktif.');
            }

            return;
        }

        if ($jenisPeringatan === SuratPeringatanDisipliner::TYPE_SP3) {
            if (!$this->hasIssuedType($karyawanId, SuratPeringatanDisipliner::TYPE_SP2, $excludeId)) {
                throw new RuntimeException('SP3 hanya dapat diterbitkan jika karyawan sudah memiliki SP2 yang aktif.');
            }

            return;
        }

        throw new RuntimeException('Jenis surat peringatan tidak valid.');
    }

    public function suggestJenisPeringatan(int $karyawanId): string
    {
        if (!$this->hasIssuedType($karyawanId, SuratPeringatanDisipliner::TYPE_SP1)) {
            return SuratPeringatanDisipliner::TYPE_SP1;
        }

        if (!$this->hasIssuedType($karyawanId, SuratPeringatanDisipliner::TYPE_SP2)) {
            return SuratPeringatanDisipliner::TYPE_SP2;
        }

        return SuratPeringatanDisipliner::TYPE_SP3;
    }

    public function resolveIdSuratSebelumnya(int $karyawanId, string $jenisPeringatan): ?int
    {
        $jenisPeringatan = strtoupper(trim($jenisPeringatan));
        $lookupType = null;

        if ($jenisPeringatan === SuratPeringatanDisipliner::TYPE_SP2) {
            $lookupType = SuratPeringatanDisipliner::TYPE_SP1;
        } elseif ($jenisPeringatan === SuratPeringatanDisipliner::TYPE_SP3) {
            $lookupType = SuratPeringatanDisipliner::TYPE_SP2;
        }

        if ($lookupType === null) {
            return null;
        }

        $previous = SuratPeringatanDisipliner::query()
            ->where('karyawan_id', $karyawanId)
            ->where('jenis_peringatan', $lookupType)
            ->where('status', SuratPeringatanDisipliner::STATUS_ISSUED)
            ->where('is_active', true)
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('id')
            ->first();

        return $previous ? (int) $previous->id : null;
    }

    public function allocateDocumentNumber(string $jenisPeringatan, ?int $year = null): array
    {
        $jenisPeringatan = strtoupper(trim($jenisPeringatan));
        $prefix = self::TYPE_PREFIX[$jenisPeringatan] ?? null;

        if ($prefix === null) {
            throw new RuntimeException('Jenis surat peringatan tidak valid.');
        }

        $year = $year ?? (int) date('Y');
        $documentType = 'SURAT_' . $jenisPeringatan;

        return DB::transaction(function () use ($documentType, $prefix, $year) {
            $now = date('Y-m-d H:i:s');

            DB::table('employee_document_sequences')->insertOrIgnore([
                'document_type' => $documentType,
                'prefix' => $prefix,
                'year' => $year,
                'last_number' => 0,
                'padding' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = EmployeeDocumentSequence::where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new RuntimeException('Sequence nomor surat peringatan gagal dibuat.');
            }

            $sequence->prefix = $prefix;
            $sequence->padding = 4;
            $sequence->last_number = ((int) $sequence->last_number) + 1;
            $sequence->save();

            $nomorSurat = $prefix
                . substr((string) $year, -2)
                . str_pad((string) $sequence->last_number, 4, '0', STR_PAD_LEFT);

            return [
                'nomor_surat' => $nomorSurat,
                'tahun' => $year,
                'urutan' => (int) $sequence->last_number,
            ];
        });
    }

    public function hasStoredPdf(SuratPeringatanDisipliner $letter): bool
    {
        $relativePath = trim((string) ($letter->file_path ?? ''));
        if ($relativePath === '') {
            return false;
        }

        $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));

        return is_file($fullPath) && filesize($fullPath) > 0;
    }

    /** Hapus arsip PDF agar view berikutnya render ulang (setelah edit / pengesahan QR). */
    public function invalidateStoredPdf(SuratPeringatanDisipliner $letter): void
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

    /**
     * Ambil PDF dari arsip disk jika ada; jika tidak, render lalu simpan.
     */
    public function resolvePdfBinary(SuratPeringatanDisipliner $letter, object $detailRow): string
    {
        if ($this->hasStoredPdf($letter)) {
            return $this->readPdfBinary($letter);
        }

        $this->generateAndStorePdf($letter, $detailRow);

        return $this->readPdfBinary($letter->fresh());
    }

    /** Paksa render ulang dari blade terbaru dan timpa arsip PDF di disk. */
    public function regenerateStoredPdf(SuratPeringatanDisipliner $letter, object $detailRow): SuratPeringatanDisipliner
    {
        $linkedQr = trim((string) ($letter->file_qr ?? ''));
        if ($linkedQr !== '') {
            GenerateQrDocumentDisipliner::ensurePublicSvgForFile($linkedQr);
        }

        $letter->load('suratSebelumnya');

        return $this->generateAndStorePdf($letter, $detailRow);
    }

    public function generateAndStorePdf(SuratPeringatanDisipliner $letter, object $detailRow): SuratPeringatanDisipliner
    {
        $previousRelativePath = trim((string) ($letter->file_path ?? ''));

        $pdfBinary = $this->renderPdf($letter, $detailRow);
        $directory = public_path(self::STORAGE_DIR . '/' . $letter->karyawan_id);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder arsip surat peringatan gagal dibuat.');
        }

        $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '_', $letter->nomor_surat);
        $fileName = $safeNumber . '.pdf';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($fullPath, $pdfBinary) === false) {
            throw new RuntimeException('Gagal menyimpan file PDF surat peringatan.');
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

    public function readPdfBinary(SuratPeringatanDisipliner $letter): string
    {
        if (!$this->hasStoredPdf($letter)) {
            throw new RuntimeException('File surat peringatan belum tersedia di server.');
        }

        $fullPath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $letter->file_path));
        $binary = file_get_contents($fullPath);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('File surat peringatan tidak dapat dibaca.');
        }

        return $binary;
    }

    public function renderPdf(SuratPeringatanDisipliner $letter, object $detailRow): string
    {
        Carbon::setLocale('id');

        $issueDate = $letter->tanggal_terbit
            ? Carbon::parse($letter->tanggal_terbit)->locale('id')
            : Carbon::now()->locale('id');

        $spMeta = $this->resolveSpMeta($letter->jenis_peringatan);
        $validityMonths = (int) ($letter->masa_berlaku_bulan ?: self::defaultMasaBerlakuBulan($spMeta['type']));
        $validityStart = $letter->tanggal_berlaku_mulai
            ? Carbon::parse($letter->tanggal_berlaku_mulai)->locale('id')
            : $issueDate->copy();
        $validityEnd = $letter->tanggal_berlaku_selesai
            ? Carbon::parse($letter->tanggal_berlaku_selesai)->locale('id')
            : $validityStart->copy()->addMonthsNoOverflow($validityMonths);

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

        $violationCategory = trim((string) $letter->jenis_pelanggaran);
        $hrNotesHtml = trim((string) $letter->catatan_hrd);
        $sanctionText = trim((string) $letter->bentuk_sanksi)
            ?: self::defaultBentukSanksi($spMeta['type']);

        $qrImagePath = $this->resolveQrImageForPdf(trim((string) ($letter->file_qr ?? '')));

        $refNumber = trim((string) $letter->nomor_surat)
            ?: $this->formatRefNumber($letter, $issueDate, $spMeta['number']);
        $headerTitle = 'SURAT PERINGATAN ' . $spMeta['number'] . ' (SP-' . $spMeta['number'] . ')';
        $headerRef = 'Ref : ' . $refNumber;

        $html = view('disipliner.surat-peringatan', [
            'spLevelNumber' => $spMeta['number'],
            'spLevelRoman' => $spMeta['roman'],
            'refNumber' => $refNumber,
            'employeeNameNik' => $employeeNameNik,
            'divisionName' => trim((string) ($detailRow->nama_divisi ?? ($detailRow->department ?? ''))) ?: '-',
            'positionName' => trim((string) ($detailRow->jabatan ?? '')) ?: '-',
            'placementName' => $placement,
            'violationCategory' => $violationCategory,
            'violationDescription' => trim((string) $letter->uraian_pelanggaran) ?: '<p>-</p>',
            'hrNotesHtml' => $hrNotesHtml !== '' ? $hrNotesHtml : '',
            'sanctionText' => $sanctionText,
            'validityMonths' => $validityMonths,
            'validityMonthsTerbilang' => $this->terbilang($validityMonths),
            'validityStartLabel' => $validityStart->translatedFormat('d F Y'),
            'validityEndLabel' => $validityEnd->translatedFormat('d F Y'),
            'honorific' => $honorific,
            'nextSpLabel' => $spMeta['next_label'],
            'issueDateLong' => $issueDate->translatedFormat('d F Y'),
            'qrImagePath' => $qrImagePath,
            'isApproved' => $letter->status === SuratPeringatanDisipliner::STATUS_ISSUED,
        ])->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];

        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 34,
            'margin_bottom' => 24,
            'margin_header' => 6,
            'margin_footer' => 10,
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

        $logo = file_exists(public_path('isl_logo.png'))
            ? public_path('isl_logo.png')
            : public_path('img/isl_logo.png');

        $headerTitleEsc = htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8');
        $headerRefEsc = htmlspecialchars($headerRef, ENT_QUOTES, 'UTF-8');

        $headerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-collapse:collapse;">'
            . '<tr><td colspan="2" style="text-align:right; font-size:8pt; color:#666; padding:0 0 1mm;">Page {PAGENO} of {nbpg}</td></tr>'
            . '<tr>'
            . '<td style="width:52mm; vertical-align:middle; padding:0 0 2mm;"><img src="' . $logo . '" style="width:48mm;"></td>'
            . '<td style="vertical-align:middle; text-align:center; padding:0 0 2mm 2mm;">'
            . '<div style="font-size:16pt; font-weight:bold; line-height:1.2;">' . $headerTitleEsc . '</div>'
            . '<div style="font-size:10pt; font-weight:bold; margin-top:1.5mm;">' . $headerRefEsc . '</div>'
            . '</td>'
            . '</tr></table>';
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

    private function hasIssuedType(int $karyawanId, string $type, ?int $excludeId = null): bool
    {
        $query = SuratPeringatanDisipliner::query()
            ->where('karyawan_id', $karyawanId)
            ->where('jenis_peringatan', $type)
            ->where('status', SuratPeringatanDisipliner::STATUS_ISSUED)
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    private function resolveQrImageForPdf(?string $qrFile): ?string
    {
        if ($qrFile === null || $qrFile === '') {
            return null;
        }

        $qrRecord = QrDocument::query()
            ->where('file', $qrFile)
            ->where('type_document', GenerateQrDocumentDisipliner::TYPE_SURAT_PERINGATAN)
            ->first();

        if ($qrRecord && !empty($qrRecord->kode_qr)) {
            $tempDir = storage_path('app/tmp-sp-qr');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $pngPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('sp_qr_', true) . '.png';

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

    private function resolveSpMeta(string $jenisPeringatan): array
    {
        $type = strtoupper(trim($jenisPeringatan));
        $map = [
            SuratPeringatanDisipliner::TYPE_SP1 => ['number' => 1, 'roman' => 'I', 'next_label' => '2 (SP-2)'],
            SuratPeringatanDisipliner::TYPE_SP2 => ['number' => 2, 'roman' => 'II', 'next_label' => '3 (SP-3)'],
            SuratPeringatanDisipliner::TYPE_SP3 => ['number' => 3, 'roman' => 'III', 'next_label' => '3 (SP-3)'],
        ];

        $meta = $map[$type] ?? $map[SuratPeringatanDisipliner::TYPE_SP1];

        return [
            'type' => $type,
            'number' => $meta['number'],
            'roman' => $meta['roman'],
            'next_label' => $meta['next_label'],
        ];
    }

    private function formatRefNumber(SuratPeringatanDisipliner $letter, Carbon $issueDate, int $spNumber): string
    {
        $year = (int) ($letter->tahun_nomor ?: $issueDate->year);
        $sequence = str_pad((string) ((int) $letter->urutan_nomor ?: 0), 4, '0', STR_PAD_LEFT);
        $romanMonth = $this->romanMonth((int) $issueDate->month);

        return sprintf(
            'ISL/09-SP%d/%d-%s/%s',
            $spNumber,
            $year,
            $romanMonth,
            $sequence
        );
    }

    private function romanMonth(int $month): string
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        return $map[$month] ?? (string) $month;
    }

    private function terbilang(int $number): string
    {
        $words = [
            '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam',
            'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
        ];

        if ($number < 12) {
            return $words[$number];
        }
        if ($number < 20) {
            return trim($this->terbilang($number - 10) . ' belas');
        }
        if ($number < 100) {
            return trim($this->terbilang(intdiv($number, 10)) . ' puluh ' . $this->terbilang($number % 10));
        }
        if ($number < 200) {
            return trim('seratus ' . $this->terbilang($number - 100));
        }
        if ($number < 1000) {
            return trim($this->terbilang(intdiv($number, 100)) . ' ratus ' . $this->terbilang($number % 100));
        }

        return (string) $number;
    }
}
