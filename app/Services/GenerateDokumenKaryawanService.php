<?php

namespace App\Services;

use App\Models\BpjsKesehatan;
use App\Models\BpjsTk;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentSequence;
use App\Models\MasterKaryawan;
use App\Models\MasterSallary;
use App\Models\NewRecruitment;
use App\Models\PencadanganUpah;
use App\Models\PenanggungJawabDokumenKaryawan;
use App\Models\PPH21;
use App\Models\RekeningKaryawan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mpdf\Output\Destination;
use RuntimeException;

class GenerateDokumenKaryawanService
{
    private const DOCUMENT_BASE_DIR = 'dokumen/dokumen-karyawan';
    private const PAGE_BACKGROUND = 'images/dokumen-karyawan-page-background.png';
    private const SPRJ_NUMBER_PREFIX = 'ISL-11-SPRJ-';
    private const SPNY_NUMBER_PREFIX = 'ISL-11-SPNY-';
    private const SINGKATAN_TETAP = [
        'DKI', 'SPV', 'HRD', 'HR', 'IT', 'QA', 'QC', 'GA', 'PPIC',
        'CEO', 'CFO', 'COO', 'GM', 'BOD', 'HSE', 'EHS', 'K3', 'ISO', 'PIC', 'ADM',
    ];

    private $temporaryBackgroundPath;

    public function generate(int $karyawanId, ?int $newRecruitmentId = null, ?int $penanggungJawabId = null, bool $penanggungJawabFromPersonnelRequest = false, ?string $generatedBy = null): array
    {
        $data = $this->prepareData($karyawanId, $newRecruitmentId, $penanggungJawabId, $penanggungJawabFromPersonnelRequest, $generatedBy);

        // Setiap generator dokumen menggunakan data bersama yang sama.
        // Tambahkan hasil generator ke array ini saat template dokumennya siap.
        return [
            'sprj' => $this->generateSprj($data),
            'coc' => $this->generateCoc($data),
            'pkkp' => $this->generatePkkp($data),
            'spkb' => $this->generateSpkb($data),
            'spny' => $this->generateSpny($data),
            'nda' => $this->isPosisiStaff($data) ? $this->generateNda($data) : null,
            'nda_spv' => $this->isPosisiAtasan($data) ? $this->generateNda($data, 'NDA-SPV', 'DokumenKaryawan.nda-spv') : null,
            'pkpp' => $this->isPosisiSampler($data) ? null : $this->generateSpny($data, 'PKPP', 'DokumenKaryawan.pkpp'),
            'pkpp_sampling' => $this->isPosisiSampler($data) ? $this->generateSpny($data, 'PKPP-S', 'DokumenKaryawan.pkpp-sampling') : null,
        ];
    }

    private function generateSprj(array $data): array
    {
        if (
            !Schema::hasTable('employee_documents')
            || !Schema::hasTable('employee_document_sequences')
        ) {
            throw new RuntimeException('Tabel dokumen karyawan atau sequence nomor dokumen belum tersedia.');
        }

        $karyawan = $data['karyawan'];
        $this->retireActiveDocuments($karyawan->id, 'SPRJ');

        $sequence = $this->nextDocumentNumber('SPRJ', self::SPRJ_NUMBER_PREFIX, (int) date('Y'), 4, 'SPRJ');
        $noDokumen = $sequence['document_number'];
        $noPengesahan = $this->nextPengesahanNumber()['document_number'];
        $filename = $noDokumen . '.pdf';
        $relativePath = $this->documentRelativePath($karyawan->id, $filename);

        $document = EmployeeDocument::create([
            'karyawan_id' => $karyawan->id,
            'new_recruitment_id' => optional($data['recruitment'])->id,
            'document_type' => 'SPRJ',
            'document_number' => $noDokumen,
            'sequence_year' => $sequence['year'],
            'sequence_number' => $sequence['number'],
            'file_name' => $filename,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => null,
            'status' => 'generating',
            'is_active' => true,
            'generated_by' => $data['generated_by'],
            'generated_at' => null,
        ]);

        $directory = $this->ensureDocumentDirectory($karyawan->id);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $pdf = $this->renderSprjPdf($data, $noDokumen, $noPengesahan);
            $pdf->Output($fullPath, Destination::FILE);

            $document->file_size = filesize($fullPath);
            $document->status = 'generated';
            $document->generated_at = Carbon::now();
            $document->save();
        } catch (\Throwable $e) {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $document->status = 'failed';
            $document->is_active = false;
            $document->save();
            throw $e;
        } finally {
            $this->clearTemporaryBackground();
        }

        return $this->documentResult($document->fresh());
    }

    private function generateCoc(array $data): array
    {
        if (
            !Schema::hasTable('employee_documents')
            || !Schema::hasTable('employee_document_sequences')
        ) {
            throw new RuntimeException('Tabel dokumen karyawan atau sequence nomor dokumen belum tersedia.');
        }

        $karyawan = $data['karyawan'];
        $this->retireActiveDocuments($karyawan->id, 'COC');

        $sequence = $this->nextCocHexNumber();
        $noDokumen = $sequence['document_number'];
        $filename = $noDokumen . '.pdf';
        $relativePath = $this->documentRelativePath($karyawan->id, $filename);

        $document = EmployeeDocument::create([
            'karyawan_id' => $karyawan->id,
            'new_recruitment_id' => optional($data['recruitment'])->id,
            'document_type' => 'COC',
            'document_number' => $noDokumen,
            'sequence_year' => $sequence['year'],
            'sequence_number' => $sequence['number'],
            'file_name' => $filename,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => null,
            'status' => 'generating',
            'is_active' => true,
            'generated_by' => $data['generated_by'],
            'generated_at' => null,
        ]);

        $directory = $this->ensureDocumentDirectory($karyawan->id);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $pdf = $this->renderCocPdf($data);
            $pdf->Output($fullPath, Destination::FILE);

            $document->file_size = filesize($fullPath);
            $document->status = 'generated';
            $document->generated_at = Carbon::now();
            $document->save();
        } catch (\Throwable $e) {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $document->status = 'failed';
            $document->is_active = false;
            $document->save();
            throw $e;
        } finally {
            $this->clearTemporaryBackground();
        }

        return $this->documentResult($document->fresh());
    }

    private function generatePkkp(array $data): array
    {
        if (
            !Schema::hasTable('employee_documents')
            || !Schema::hasTable('employee_document_sequences')
        ) {
            throw new RuntimeException('Tabel dokumen karyawan atau sequence nomor dokumen belum tersedia.');
        }

        $karyawan = $data['karyawan'];
        $this->retireActiveDocuments($karyawan->id, 'PKKP');

        $sequence = $this->nextDocumentNumber('SPNY', self::SPNY_NUMBER_PREFIX, (int) date('Y'), 4, 'PKKP');
        $noDokumen = $sequence['document_number'];
        $filename = $noDokumen . '.pdf';
        $relativePath = $this->documentRelativePath($karyawan->id, $filename);

        $document = EmployeeDocument::create([
            'karyawan_id' => $karyawan->id,
            'new_recruitment_id' => optional($data['recruitment'])->id,
            'document_type' => 'PKKP',
            'document_number' => $noDokumen,
            'sequence_year' => $sequence['year'],
            'sequence_number' => $sequence['number'],
            'file_name' => $filename,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => null,
            'status' => 'generating',
            'is_active' => true,
            'generated_by' => $data['generated_by'],
            'generated_at' => null,
        ]);

        $directory = $this->ensureDocumentDirectory($karyawan->id);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $pdf = $this->renderPkkpPdf($data, $noDokumen);
            $pdf->Output($fullPath, Destination::FILE);

            $document->file_size = filesize($fullPath);
            $document->status = 'generated';
            $document->generated_at = Carbon::now();
            $document->save();
        } catch (\Throwable $e) {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $document->status = 'failed';
            $document->is_active = false;
            $document->save();
            throw $e;
        } finally {
            $this->clearTemporaryBackground();
        }

        return $this->documentResult($document->fresh());
    }

    private function generateSpkb(array $data): array
    {
        if (
            !Schema::hasTable('employee_documents')
            || !Schema::hasTable('employee_document_sequences')
        ) {
            throw new RuntimeException('Tabel dokumen karyawan atau sequence nomor dokumen belum tersedia.');
        }

        $karyawan = $data['karyawan'];
        $this->retireActiveDocuments($karyawan->id, 'SPKB');

        $sequence = $this->nextDocumentNumber('SPNY', self::SPNY_NUMBER_PREFIX, (int) date('Y'), 4, 'SPKB');
        $noDokumen = $sequence['document_number'];
        $filename = $noDokumen . '.pdf';
        $relativePath = $this->documentRelativePath($karyawan->id, $filename);

        $document = EmployeeDocument::create([
            'karyawan_id' => $karyawan->id,
            'new_recruitment_id' => optional($data['recruitment'])->id,
            'document_type' => 'SPKB',
            'document_number' => $noDokumen,
            'sequence_year' => $sequence['year'],
            'sequence_number' => $sequence['number'],
            'file_name' => $filename,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => null,
            'status' => 'generating',
            'is_active' => true,
            'generated_by' => $data['generated_by'],
            'generated_at' => null,
        ]);

        $directory = $this->ensureDocumentDirectory($karyawan->id);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $pdf = $this->renderSpkbPdf($data, $noDokumen);
            $pdf->Output($fullPath, Destination::FILE);

            $document->file_size = filesize($fullPath);
            $document->status = 'generated';
            $document->generated_at = Carbon::now();
            $document->save();
        } catch (\Throwable $e) {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $document->status = 'failed';
            $document->is_active = false;
            $document->save();
            throw $e;
        } finally {
            $this->clearTemporaryBackground();
        }

        return $this->documentResult($document->fresh());
    }

    private function generateSpny(
        array $data,
        string $documentType = 'SPNY',
        string $view = 'DokumenKaryawan.spny'
    ): array
    {
        if (
            !Schema::hasTable('employee_documents')
            || !Schema::hasTable('employee_document_sequences')
        ) {
            throw new RuntimeException('Tabel dokumen karyawan atau sequence nomor dokumen belum tersedia.');
        }

        $karyawan = $data['karyawan'];
        $this->retireActiveDocuments($karyawan->id, $documentType);

        $sequence = $this->nextDocumentNumber('SPNY', self::SPNY_NUMBER_PREFIX, (int) date('Y'), 4, $documentType);
        $noDokumen = $sequence['document_number'];
        $filename = $noDokumen . '.pdf';
        $relativePath = $this->documentRelativePath($karyawan->id, $filename);

        $document = EmployeeDocument::create([
            'karyawan_id' => $karyawan->id,
            'new_recruitment_id' => optional($data['recruitment'])->id,
            'document_type' => $documentType,
            'document_number' => $noDokumen,
            'sequence_year' => $sequence['year'],
            'sequence_number' => $sequence['number'],
            'file_name' => $filename,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => null,
            'status' => 'generating',
            'is_active' => true,
            'generated_by' => $data['generated_by'],
            'generated_at' => null,
        ]);

        $directory = $this->ensureDocumentDirectory($karyawan->id);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $pdf = $this->renderSpnyPdf($data, $noDokumen, $view);
            $pdf->Output($fullPath, Destination::FILE);

            $document->file_size = filesize($fullPath);
            $document->status = 'generated';
            $document->generated_at = Carbon::now();
            $document->save();
        } catch (\Throwable $e) {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $document->status = 'failed';
            $document->is_active = false;
            $document->save();
            throw $e;
        } finally {
            $this->clearTemporaryBackground();
        }

        return $this->documentResult($document->fresh());
    }

    private function generateNda(
        array $data,
        string $documentType = 'NDA',
        string $view = 'DokumenKaryawan.nda'
    ): array
    {
        if (
            !Schema::hasTable('employee_documents')
            || !Schema::hasTable('employee_document_sequences')
        ) {
            throw new RuntimeException('Tabel dokumen karyawan atau sequence nomor dokumen belum tersedia.');
        }

        $karyawan = $data['karyawan'];
        $this->retireActiveDocuments($karyawan->id, $documentType);

        $sequence = $this->nextDocumentNumber('SPRJ', self::SPRJ_NUMBER_PREFIX, (int) date('Y'), 4, $documentType);
        $noDokumen = $sequence['document_number'];
        $filename = $noDokumen . '.pdf';
        $relativePath = $this->documentRelativePath($karyawan->id, $filename);

        $document = EmployeeDocument::create([
            'karyawan_id' => $karyawan->id,
            'new_recruitment_id' => optional($data['recruitment'])->id,
            'document_type' => $documentType,
            'document_number' => $noDokumen,
            'sequence_year' => $sequence['year'],
            'sequence_number' => $sequence['number'],
            'file_name' => $filename,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => null,
            'status' => 'generating',
            'is_active' => true,
            'generated_by' => $data['generated_by'],
            'generated_at' => null,
        ]);

        $directory = $this->ensureDocumentDirectory($karyawan->id);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        try {
            $pdf = $this->renderNdaPdf($data, $noDokumen, $view);
            $pdf->Output($fullPath, Destination::FILE);

            $document->file_size = filesize($fullPath);
            $document->status = 'generated';
            $document->generated_at = Carbon::now();
            $document->save();
        } catch (\Throwable $e) {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $document->status = 'failed';
            $document->is_active = false;
            $document->save();
            throw $e;
        } finally {
            $this->clearTemporaryBackground();
        }

        return $this->documentResult($document->fresh());
    }

    private function isPosisiStaff(array $data): bool
    {
        $karyawan = $data['karyawan'];
        $grade = strtoupper(trim((string) (
            ($karyawan->kategori_grade ?? null)
            ?: ($karyawan->grade ?? null)
            ?: optional(optional($data['recruitment'])->personnelRequest)->grade_master_karyawan
        )));

        if (in_array($grade, ['SUPERVISOR', 'MANAGER'], true)) {
            return false;
        }

        if ($grade === 'STAFF') {
            return true;
        }

        $posisi = strtoupper(trim((string) (
            ($karyawan->getAttributes()['jabatan'] ?? null)
            ?: optional($karyawan->getRelation('jabatan'))->nama_jabatan
        )));

        return (bool) preg_match('/\bSTAFF\b/', $posisi);
    }

    private function isPosisiAtasan(array $data): bool
    {
        $karyawan = $data['karyawan'];
        $grade = strtoupper(trim((string) (
            ($karyawan->kategori_grade ?? null)
            ?: ($karyawan->grade ?? null)
            ?: optional(optional($data['recruitment'])->personnelRequest)->grade_master_karyawan
        )));

        if (in_array($grade, ['SUPERVISOR', 'MANAGER', 'SPV'], true)) {
            return true;
        }

        if ($grade === 'STAFF') {
            return false;
        }

        $posisi = strtoupper(trim((string) (
            ($karyawan->getAttributes()['jabatan'] ?? null)
            ?: optional($karyawan->getRelation('jabatan'))->nama_jabatan
        )));

        return (bool) preg_match('/\b(SUPERVISOR|MANAGER|SPV)\b/', $posisi);
    }

    private function isPosisiSampler(array $data): bool
    {
        $karyawan = $data['karyawan'];
        if ((int) $karyawan->id_jabatan === 94) {
            return true;
        }

        $posisi = strtoupper(trim((string) (
            ($karyawan->getAttributes()['jabatan'] ?? null)
            ?: optional($karyawan->getRelation('jabatan'))->nama_jabatan
            ?: optional(optional($data['recruitment'])->personnelRequest)->posisi
        )));

        return (bool) preg_match('/\bSAMPLER\b/', $posisi);
    }

    private function renderCocPdf(array $data): MpdfService
    {
        $karyawan = $data['karyawan'];
        $html = view('DokumenKaryawan.coc', [
            'namaKaryawan' => $this->camelCaseTeks($karyawan->nama_lengkap) ?: '-',
            'tanggalPengesahan' => $this->formatTanggalIndonesia(Carbon::now()),
        ])->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 22,
            'margin_right' => 22,
            'margin_top' => 32,
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

        $logo = public_path('isl_logo.png');
        $headerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-collapse:collapse;">'
            . '<tr><td style="padding:0;">'
            . '<table width="100%" style="border-collapse:collapse; border-bottom:0.4px solid #9a9a9a;">'
            . '<tr><td colspan="2" style="text-align:right; font-size:8pt; color:#666; padding:0 0 1mm;">Page {PAGENO} of {nbpg}</td></tr>'
            . '<tr>'
            . '<td style="width:48mm; padding:1mm 0 2mm;"><img src="' . $logo . '" style="width:46mm;"></td>'
            . '<td style="text-align:center; vertical-align:middle; color:#ff0000; font-size:14.5pt; font-weight:bold; white-space:nowrap;">'
            . 'ATURAN PERILAKU (<span style="font-style:italic;">Code of Conduct</span>)</td>'
            . '</tr>'
            . '<tr><td colspan="2" style="border-top:0.4px solid #9a9a9a; font-size:1pt; line-height:0.4; padding:0;">&nbsp;</td></tr>'
            . '</table></td></tr></table>';
        $pdf->SetHTMLHeader($headerHtml);

        $footerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-top:0.4px solid #9a9a9a;">'
            . '<tr><td style="text-align:center; font-size:10pt; line-height:1.25; padding-top:2mm;">'
            . 'Ruko Icon Business Park blok O no. 5 – 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com</td></tr></table>';
        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $pdf->SetWatermarkImage($this->documentPageBackground(), 1, [210, 297], [0, 0]);
        $pdf->showWatermarkImage = true;

        $pdf->WriteHTML($html);

        return $pdf;
    }

    private function renderPkkpPdf(array $data, string $noDokumen): MpdfService
    {
        $html = view('DokumenKaryawan.pkkp', $this->buildPkkpViewData($data, $noDokumen))->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 8,
            'margin_bottom' => 25,
            'margin_header' => 6,
            'margin_footer' => 8,
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

        $footerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-top:0.4px solid #9a9a9a;">'
            . '<tr><td style="text-align:center; font-size:9.5pt; line-height:1.25; padding-top:1.5mm;">'
            . 'Ruko Icon Business Park blok O no. 5 – 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com</td></tr></table>';
        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $pdf->SetWatermarkImage($this->documentPageBackground(), 1, [210, 297], [0, 0]);
        $pdf->showWatermarkImage = true;
        $pdf->WriteHTML($html);

        return $pdf;
    }

    private function renderSpkbPdf(array $data, string $noDokumen): MpdfService
    {
        $html = view('DokumenKaryawan.spkb', $this->buildSpkbViewData($data, $noDokumen))->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 8,
            'margin_bottom' => 22,
            'margin_header' => 6,
            'margin_footer' => 8,
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

        $footerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-top:0.4px solid #9a9a9a;">'
            . '<tr><td style="text-align:center; font-size:9.5pt; line-height:1.25; padding-top:1.5mm;">'
            . 'Ruko Icon Business Park blok O no. 5 – 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com</td></tr></table>';
        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $pdf->SetWatermarkImage($this->documentPageBackground(), 1, [210, 297], [0, 0]);
        $pdf->showWatermarkImage = true;
        $pdf->WriteHTML($html);

        return $pdf;
    }

    private function renderSpnyPdf(
        array $data,
        string $noDokumen,
        string $view = 'DokumenKaryawan.spny'
    ): MpdfService {
        $html = view($view, $this->buildSpnyViewData($data, $noDokumen))->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 30,
            'margin_bottom' => 25,
            'margin_header' => 7,
            'margin_footer' => 8,
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

        $logo = public_path('isl_logo.png');
        $headerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-collapse:collapse;">'
            . '<tr><td colspan="2" style="text-align:right; font-size:8pt; color:#666; padding:0 0 0.6mm;">Page {PAGENO} of {nbpg}</td></tr>'
            . '<tr>'
            . '<td style="width:52mm; border-bottom:0.6px solid #111; vertical-align:middle; padding:0 0 1.4mm;"><img src="' . $logo . '" style="width:48mm;"></td>'
            . '<td style="border-bottom:0.6px solid #111; text-align:center; padding:0 0 1.4mm 22mm;">'
            . '<div style="text-align:center; font-size:18pt; font-weight:bold; line-height:1.05; text-decoration:underline;">SURAT PERNYATAAN</div>'
            . '<div style="text-align:center; font-size:10pt; font-weight:bold; margin-top:1mm;">No. : ' . e($noDokumen) . '</div>'
            . '</td></tr></table>';
        $pdf->SetHTMLHeader($headerHtml);
        $pdf->SetHTMLHeader($headerHtml, 'E');

        $footerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-top:0.4px solid #9a9a9a;">'
            . '<tr><td style="text-align:center; font-size:9.5pt; line-height:1.25; padding-top:1.5mm;">'
            . 'Ruko Icon Business Park blok O no. 5 – 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com</td></tr></table>';
        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $pdf->SetWatermarkImage($this->documentPageBackground(), 1, [210, 297], [0, 0]);
        $pdf->showWatermarkImage = true;
        $pdf->WriteHTML($html);

        return $pdf;
    }

    private function renderNdaPdf(
        array $data,
        string $noDokumen,
        string $view = 'DokumenKaryawan.nda'
    ): MpdfService {
        $html = view($view, $this->buildNdaViewData($data, $noDokumen))->render();

        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 5,
            'margin_bottom' => 25,
            'margin_header' => 7,
            'margin_footer' => 8,
            'setAutoTopMargin' => 'stretch',
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

        $logo = public_path('isl_logo.png');
        $headerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-collapse:collapse;">'
            . '<tr><td colspan="2" style="text-align:right; font-size:8pt; color:#666; padding:0 0 0.6mm;">Page {PAGENO} of {nbpg}</td></tr>'
            . '<tr>'
            . '<td style="width:52mm; border-bottom:0.6px solid #111; vertical-align:middle; padding:0 0 1.4mm;"><img src="' . $logo . '" style="width:48mm;"></td>'
            . '<td style="border-bottom:0.6px solid #111; text-align:center; padding:0 0 1.4mm 10mm;">'
            . '<div style="text-align:center; font-size:13pt; font-weight:bold; line-height:1.15;">PERJANJIAN KERAHASIAAN &amp;<br>NON-KOMPETISI</div>'
            . '<div style="text-align:center; font-size:10pt; font-weight:bold; margin-top:1mm;">No. : ' . e($noDokumen) . '</div>'
            . '</td></tr>'
            . '<tr><td colspan="2" style="height:3mm; font-size:1pt; line-height:1pt;">&nbsp;</td></tr></table>';
        $pdf->SetHTMLHeader($headerHtml);
        $pdf->SetHTMLHeader($headerHtml, 'E');

        $footerHtml =
            '<table width="100%" style="font-family:calibri,sans-serif; border-top:0.4px solid #9a9a9a;">'
            . '<tr><td style="text-align:center; font-size:9.5pt; line-height:1.25; padding-top:1.5mm;">'
            . 'Ruko Icon Business Park blok O no. 5 – 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang – 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com</td></tr></table>';
        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $pdf->SetWatermarkImage($this->documentPageBackground(), 1, [210, 297], [0, 0]);
        $pdf->showWatermarkImage = true;
        $pdf->WriteHTML($html);

        return $pdf;
    }

    private function renderSprjPdf(array $data, string $noDokumen, string $noPengesahan): MpdfService
    {
        $viewData = $this->buildSprjViewData($data, $noDokumen, $noPengesahan);
        $html = view('DokumenKaryawan.sprj', $viewData)->render();

        $pdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 28,
            'margin_bottom' => 22,
            'margin_header' => 5,
            'margin_footer' => 10,
        ]);

        $logo = public_path('isl_logo.png');
        $headerHtml =
            '<table width="100%" style="width:100%; font-family:Arial,sans-serif; border-collapse:collapse;">'
            . '<tr>'
            . '<td width="34%" style="padding:2mm 0 3mm;"><img src="' . $logo . '" style="width:55mm;"></td>'
            . '<td width="55%" style="padding-left:40mm; text-align:center; vertical-align:middle; color:#111; white-space:nowrap;">'
            . '<div style="font-size:18px; font-weight:bold; color:#000;">PERJANJIAN MASA PELATIHAN</div>'
            . '<div style="font-size:12px; color:#111; font-weight:bold;">No. : ' . e($noDokumen) . '</div></td>'
            . '<td width="8%" style="text-align:right; vertical-align:top; padding-top:1mm; font-size:10px; color:#555;">'
            . 'Page {PAGENO} of {nbpg}</td>'
            . '</tr>'
            . '<tr><td colspan="3" style="border-bottom:0.4px solid #9a9a9a; height:0; line-height:0; padding:0;"></td></tr>'
            . '</table>';
        $pdf->SetHTMLHeader($headerHtml);

        $footerAddress =
            '<td width="50%" style="text-align:center; font-size:10px; line-height:1.25; padding-top:2mm;">'
            . 'Ruko Icon Business Park blok O no. 5 - 6<br>'
            . 'Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang - 15345<br>'
            . 'P : 021-5089-8988/89 - www.intilab.com - contact@intilab.com</td>';
        $footerBoxes =
            '<td width="25%" style="padding-left:3mm; padding-top:2mm;">'
            . '<table width="100%" style="border-collapse:collapse; font-size:10px; color:#666;">'
            . '<tr><td style="border:0.5px solid #777; height:8mm; text-align:center; vertical-align:bottom; font-weight:bold;">Perusahaan</td>'
            . '<td style="border:0.5px solid #777; height:8mm; text-align:center; vertical-align:bottom; font-weight:bold;">Karyawan</td></tr>'
            . '</table></td>';
        $footerHtml = '<table width="100%" style="width:100%; font-family:Arial,sans-serif; border-collapse:collapse;">'
            . '<tr><td colspan="3" style="border-top:0.4px solid #9a9a9a; height:0; line-height:0; padding:0;"></td></tr>'
            . '<tr><td width="25%"></td>' . $footerAddress . $footerBoxes . '</tr></table>';
        $footerLastHtml = '<table width="100%" style="width:100%; font-family:Arial,sans-serif; border-collapse:collapse;">'
            . '<tr><td colspan="3" style="border-top:0.4px solid #9a9a9a; height:0; line-height:0; padding:0;"></td></tr>'
            . '<tr><td width="25%"></td>' . $footerAddress . '<td width="25%"></td></tr></table>';
        $pdf->SetHTMLFooter($footerHtml);
        $pdf->SetHTMLFooter($footerHtml, 'E');

        $pdf->SetWatermarkImage($this->documentPageBackground(), 1, [210, 297], [0, 0]);
        $pdf->showWatermarkImage = true;
        $pdf->WriteHTML($html);
        $pdf->SetHTMLFooter($footerLastHtml);
        $pdf->SetHTMLFooter($footerLastHtml, 'E');

        return $pdf;
    }

    private function documentPageBackground(): string
    {
        return $this->pageBackgroundWithNote(resource_path(self::PAGE_BACKGROUND));
    }

    private function pageBackgroundWithNote(string $source): string
    {
        $image = imagecreatefrompng($source);
        $font = $this->sprjNoteFontPath();

        if (!$image || !$font) {
            if (is_resource($image)) {
                imagedestroy($image);
            }

            return $source;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $issuedAt = Carbon::now()->format('YmdHi');
        $color = imagecolorallocate($image, 0, 0, 0);
        $scale = imagesx($image) / 210;
        $size = 10.5;
        $right = (int) round(202 * $scale);
        $lineGap = (int) round(2.6 * $scale);
        $borderY = $this->shrinkBottomBorderForNote($image, $this->bottomBorderRow($image), $scale, $lineGap);
        $roomBelow = imagesy($image) - $borderY;
        if ($roomBelow > $lineGap + (int) round(4 * $scale)) {
            $firstBaseline = $borderY + (int) round(4.1 * $scale);
            $secondBaseline = $firstBaseline + $lineGap;
        } else {
            $right = (int) round(196 * $scale);
            $secondBaseline = $borderY - (int) round(1.4 * $scale);
            $firstBaseline = $secondBaseline - $lineGap;
        }

        $this->drawRightAlignedText(
            $image,
            $font,
            $size,
            $right,
            $firstBaseline,
            $color,
            'Note : Dokumen ini diterbitkan otomatis oleh sistem'
        );
        $this->drawRightAlignedText(
            $image,
            $font,
            $size,
            $right,
            $secondBaseline,
            $color,
            $issuedAt
        );

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sprj-bg-' . uniqid('', true) . '.png';
        imagepng($image, $path);
        imagedestroy($image);
        $this->temporaryBackgroundPath = $path;

        return $path;
    }

    private function shrinkBottomBorderForNote($image, int $borderY, float $scale, int $lineGap): int
    {
        $height = imagesy($image);
        $width = imagesx($image);
        $neededBelow = $lineGap + (int) round(6.5 * $scale);
        if (($height - $borderY) >= $neededBelow) {
            return $borderY;
        }

        $target = $height - $neededBelow;
        $left = null;
        $right = null;
        for ($x = 0; $x < $width; $x++) {
            if ($this->isBorderPixel($image, $x, $borderY)) {
                if ($left === null) {
                    $left = $x;
                }
                $right = $x;
            }
        }

        if ($left === null || $right === null || $target >= $borderY) {
            return $borderY;
        }

        imagealphablending($image, false);
        $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
        for ($y = $target; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($this->isBorderPixel($image, $x, $y)) {
                    imagesetpixel($image, $x, $y, $clear);
                }
            }
        }

        imagealphablending($image, true);
        $stroke = imagecolorallocate($image, 17, 17, 17);
        for ($y = $target - 1; $y <= $target; $y++) {
            for ($x = $left; $x <= $right; $x++) {
                imagesetpixel($image, $x, $y, $stroke);
            }
        }

        return $target;
    }

    private function isBorderPixel($image, int $x, int $y): bool
    {
        $pixel = imagecolorat($image, $x, $y);
        $alpha = ($pixel >> 24) & 0x7F;

        return $alpha < 20
            && (($pixel >> 16) & 0xFF) < 40
            && (($pixel >> 8) & 0xFF) < 40
            && ($pixel & 0xFF) < 40;
    }

    private function bottomBorderRow($image): int
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = $height - 1; $y > $height - 120; $y--) {
            $dark = 0;
            for ($x = 80; $x < $width - 80; $x += 4) {
                $pixel = imagecolorat($image, $x, $y);
                $alpha = ($pixel >> 24) & 0x7F;
                $red = ($pixel >> 16) & 0xFF;
                if ($alpha < 80 && $red < 80) {
                    $dark++;
                }
            }

            if ($dark > 40) {
                return $y;
            }
        }

        return (int) round(288 * ($width / 210));
    }

    private function drawRightAlignedText($image, string $font, float $size, int $right, int $baseline, int $color, string $text): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        imagettftext($image, $size, 0, $right - $textWidth, $baseline, $color, $font, $text);
    }

    private function sprjNoteFontPath(): ?string
    {
        $fonts = [
            resource_path('fonts/Calibri/ariali.ttf'),
            'C:\\Windows\\Fonts\\ariali.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf',
        ];

        foreach ($fonts as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        return null;
    }

    private function clearTemporaryBackground(): void
    {
        if ($this->temporaryBackgroundPath && is_file($this->temporaryBackgroundPath)) {
            @unlink($this->temporaryBackgroundPath);
        }

        $this->temporaryBackgroundPath = null;
    }

    private function documentRelativePath(int $karyawanId, string $filename): string
    {
        return self::DOCUMENT_BASE_DIR . '/' . $karyawanId . '/' . $filename;
    }

    private function ensureDocumentDirectory(int $karyawanId): string
    {
        $directory = public_path(self::DOCUMENT_BASE_DIR . '/' . $karyawanId);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder dokumen karyawan gagal dibuat.');
        }

        return $directory;
    }

    private function nextDocumentNumber(
        string $poolType,
        string $prefix,
        int $year,
        int $padding = 4,
        ?string $ownerDocumentType = null
    ): array {
        $ownerDocumentType = $ownerDocumentType ?: $poolType;
        $yearSuffix = substr((string) $year, -2);
        $maxNumber = (10 ** $padding) - 1;

        return DB::transaction(function () use ($poolType, $prefix, $year, $padding, $ownerDocumentType, $yearSuffix, $maxNumber) {
            $now = date('Y-m-d H:i:s');

            DB::table('employee_document_sequences')->insertOrIgnore([
                'document_type' => $poolType,
                'prefix' => $prefix,
                'year' => $year,
                'last_number' => 0,
                'padding' => $padding,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = EmployeeDocumentSequence::where('document_type', $poolType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new RuntimeException('Sequence nomor dokumen gagal dibuat.');
            }

            $issued = (int) DB::table('employee_documents')
                ->where('document_number', 'like', $prefix . $yearSuffix . '%')
                ->max(DB::raw('CAST(RIGHT(document_number, ' . (int) $padding . ') AS UNSIGNED)'));

            if ((int) $sequence->last_number < $issued) {
                $sequence->last_number = $issued;
            }

            $documentNumber = null;
            do {
                $sequence->last_number = ((int) $sequence->last_number) + 1;
                if ((int) $sequence->last_number > $maxNumber) {
                    throw new RuntimeException('Sequence nomor dokumen tahun berjalan sudah penuh.');
                }

                $documentNumber = $prefix
                    . $yearSuffix
                    . str_pad((string) $sequence->last_number, $padding, '0', STR_PAD_LEFT);

                $taken = EmployeeDocument::query()
                    ->where('document_number', $documentNumber)
                    ->exists()
                    || EmployeeDocument::query()
                        ->where('document_type', $ownerDocumentType)
                        ->where('sequence_year', $year)
                        ->where('sequence_number', (int) $sequence->last_number)
                        ->exists();
            } while ($taken);

            $sequence->prefix = $prefix;
            $sequence->padding = $padding;
            $sequence->save();

            return [
                'document_number' => $documentNumber,
                'year' => $year,
                'number' => (int) $sequence->last_number,
            ];
        });
    }

    private function nextCocHexNumber(): array
    {
        $year = (int) date('Y');

        return DB::transaction(function () use ($year) {
            $now = date('Y-m-d H:i:s');

            DB::table('employee_document_sequences')->insertOrIgnore([
                'document_type' => 'COC',
                'prefix' => '',
                'year' => $year,
                'last_number' => 0,
                'padding' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = EmployeeDocumentSequence::where('document_type', 'COC')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new RuntimeException('Sequence nomor COC gagal dibuat.');
            }

            for ($attempt = 0; $attempt < 10; $attempt++) {
                $hex = strtoupper(bin2hex(random_bytes(3)));
                $number = hexdec($hex);
                $taken = EmployeeDocument::query()
                    ->where('document_number', $hex)
                    ->exists()
                    || EmployeeDocument::query()
                        ->where('document_type', 'COC')
                        ->where('sequence_year', $year)
                        ->where('sequence_number', $number)
                        ->exists();

                if (!$taken) {
                    return [
                        'document_number' => $hex,
                        'year' => $year,
                        'number' => $number,
                    ];
                }
            }

            throw new RuntimeException('Nomor dokumen COC gagal dibuat.');
        });
    }

    private function nextPengesahanNumber(): array
    {
        $now = Carbon::now();

        return DB::transaction(function () use ($now) {
            $year = (int) $now->year;
            $month = (int) $now->month;
            $documentType = 'LPD.INT';
            $prefix = 'ISL/LPD.INT/';
            $padding = 3;
            $timestamp = $now->format('Y-m-d H:i:s');

            DB::table('employee_document_sequences')->insertOrIgnore([
                'document_type' => $documentType,
                'prefix' => $prefix,
                'year' => $year,
                'last_number' => 0,
                'padding' => $padding,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $sequence = EmployeeDocumentSequence::where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new RuntimeException('Sequence nomor pengesahan gagal dibuat.');
            }

            $sequence->prefix = $prefix;
            $sequence->padding = $padding;
            $sequence->last_number = ((int) $sequence->last_number) + 1;
            $sequence->save();

            return [
                'document_number' => $this->formatNomorPengesahan($year, $month, (int) $sequence->last_number),
                'year' => $year,
                'number' => (int) $sequence->last_number,
            ];
        });
    }

    private function formatNomorPengesahan(int $year, int $month, int $number): string
    {
        $roman = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        return 'ISL/LPD.INT/'
            . substr((string) $year, -2)
            . '-'
            . $roman[$month]
            . '/'
            . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    private function generatedByName(?string $generatedBy): string
    {
        $name = trim((string) $generatedBy);
        if ($name === '') {
            return 'System';
        }

        return function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);
    }

    private function retireActiveDocuments(int $karyawanId, string $documentType): void
    {
        $rows = EmployeeDocument::query()
            ->where('karyawan_id', $karyawanId)
            ->where('document_type', $documentType)
            ->where('status', 'generated')
            ->where('is_active', 1)
            ->get();

        foreach ($rows as $existing) {
            $fullPath = public_path($existing->file_path);
            if (!is_file($fullPath)) {
                $existing->status = 'missing';
                $existing->is_active = false;
                $existing->save();
                continue;
            }

            $basename = basename($fullPath);
            $historyName = strpos($basename, 'history-') === 0 ? $basename : 'history-' . $basename;
            $historyPath = dirname($fullPath) . DIRECTORY_SEPARATOR . $historyName;
            if ($historyName !== $basename) {
                if (is_file($historyPath)) {
                    throw new RuntimeException('File riwayat dokumen sudah ada: ' . $historyName);
                }
                if (!@rename($fullPath, $historyPath)) {
                    throw new RuntimeException('File dokumen lama gagal diubah namanya.');
                }
                $existing->file_name = $historyName;
                $existing->file_path = $this->documentRelativePath($karyawanId, $historyName);
            }

            $existing->status = 'replaced';
            $existing->is_active = false;
            $existing->save();
        }
    }

    private function prepareData(int $karyawanId, ?int $newRecruitmentId = null, ?int $penanggungJawabId = null, bool $penanggungJawabFromPersonnelRequest = false, ?string $generatedBy = null): array
    {
        $karyawan = MasterKaryawan::with([
            'jabatan',
            'department',
            'cabang',
            'medical',
        ])->find($karyawanId);

        if (!$karyawan) {
            throw new RuntimeException('Karyawan tidak ditemukan.');
        }

        $recruitment = null;
        if ($newRecruitmentId) {
            $recruitment = NewRecruitment::with([
                'personnelRequest',
                'candidateDataOffer',
                'salaryOffer',
            ])->find($newRecruitmentId);
            if (!$recruitment) {
                throw new RuntimeException('Data recruitment tidak ditemukan.');
            }
        }

        $penanggungJawab = null;
        $namaPenanggungJawab = null;
        $jabatanPenanggungJawab = null;
        if ($penanggungJawabId) {
            $penanggungJawabRecord = PenanggungJawabDokumenKaryawan::query()
                ->where('id', $penanggungJawabId)
                ->where('is_active', 1)
                ->first();
            if (!$penanggungJawabRecord) {
                throw new RuntimeException('Penanggung jawab tidak ditemukan.');
            }
            $namaPenanggungJawab = $this->camelCaseTeks($penanggungJawabRecord->nama_karyawan);
            $jabatanPenanggungJawab = $this->camelCaseTeks($penanggungJawabRecord->jabatan);
        } elseif ($penanggungJawabFromPersonnelRequest && $recruitment) {
            $namaDariPermintaan = trim((string) optional($recruitment->personnelRequest)->created_by);
            if ($namaDariPermintaan !== '') {
                $penanggungJawab = MasterKaryawan::with('jabatan')
                    ->where('nama_lengkap', $namaDariPermintaan)
                    ->where('is_active', true)
                    ->first();
            }
            if ($penanggungJawab) {
                $namaPenanggungJawab = $this->camelCaseTeks($penanggungJawab->nama_lengkap);
                $jabatanRelasi = $penanggungJawab->jabatan;
                $jabatanPenanggungJawab = $this->camelCaseTeks(is_object($jabatanRelasi)
                    ? ($jabatanRelasi->nama_jabatan ?? null)
                    : $jabatanRelasi);
            }
        }

        return [
            'karyawan' => $karyawan,
            'recruitment' => $recruitment,
            'salary' => MasterSallary::where('id_karyawan', $karyawan->id)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first(),
            'rekening' => RekeningKaryawan::where('id_karyawan', $karyawan->id)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first(),
            'bpjs_kesehatan' => BpjsKesehatan::where('id_karyawan', $karyawan->id)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first(),
            'bpjs_tk' => BpjsTk::where('id_karyawan', $karyawan->id)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first(),
            'pph_21' => PPH21::where('id_karyawan', $karyawan->id)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first(),
            'pencadangan_upah' => PencadanganUpah::where('id_karyawan', $karyawan->id)
                ->where('is_active', 1)
                ->orderByDesc('id')
                ->first(),
            'penanggung_jawab' => [
                'karyawan' => $penanggungJawab,
                'nama' => $namaPenanggungJawab,
                'jabatan' => $jabatanPenanggungJawab,
            ],
            'generated_by' => $this->generatedByName($generatedBy),
            'tanggal' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'tahun' => date('Y'),
            ],
        ];
    }

    private function buildPkkpViewData(array $data, string $noDokumen): array
    {
        $karyawan = $data['karyawan'];
        $recruitment = $data['recruitment'] ?? null;
        $profile = null;
        if ($recruitment) {
            $recruitment->loadMissing('candidateProfile');
            $profile = $recruitment->candidateProfile;
        }
        $posisi = $this->jabatanDokumen($karyawan);
        $divisi = ($karyawan->getAttributes()['department'] ?? null)
            ?: (optional($karyawan->getRelation('department'))->nama_divisi ?: null);
        $penempatan = optional($karyawan->cabang)->nama_cabang ?: null;
        $nama = $this->camelCaseTeks($karyawan->nama_lengkap ?: optional($recruitment)->nama_lengkap);

        $tempatLahir = $this->camelCaseTeks($karyawan->tempat_lahir ?: optional($recruitment)->tempat_lahir);
        $tanggalLahir = $karyawan->tanggal_lahir ?: optional($recruitment)->tanggal_lahir;
        $tanggalLahirText = $tanggalLahir ? $this->formatTanggalIndonesia(Carbon::parse($tanggalLahir)) : '';
        $tempatTanggalLahir = trim($tempatLahir . ($tempatLahir && $tanggalLahirText ? ', ' : '') . $tanggalLahirText);

        $posisiPenempatan = $posisi;
        if ($penempatan) {
            $posisiPenempatan = $posisi ? $posisi . ' / ' . $penempatan : $penempatan;
        }

        return [
            'logo' => public_path('isl_logo.png'),
            'noDokumen' => $noDokumen,
            'namaLengkap' => $nama ?: '-',
            'noKtp' => $karyawan->nik_ktp ?: (optional($profile)->nik_ktp ?: '-'),
            'tempatTanggalLahir' => $tempatTanggalLahir !== '' ? $tempatTanggalLahir : '-',
            'alamatLengkap' => $this->alamatDokumen($karyawan, $profile),
            'noHp' => $karyawan->no_telpon ?: (optional($recruitment)->no_telepon ?: '-'),
            'divisiSubdivisi' => $divisi ?: '-',
            'posisiPenempatan' => $posisiPenempatan ?: '-',
            'namaKaryawan' => $nama ?: '-',
            'posisiKaryawan' => $posisi ?: '-',
            'tanggalPengesahan' => $this->formatTanggalIndonesia(Carbon::now()),
        ];
    }

    private function buildSpkbViewData(array $data, string $noDokumen): array
    {
        $view = $this->buildPkkpViewData($data, $noDokumen);
        $karyawan = $data['karyawan'];
        $recruitment = $data['recruitment'] ?? null;
        $nik = trim((string) ($karyawan->nik_karyawan ?: ''));
        $nama = $view['namaLengkap'];
        $genderRaw = trim((string) ($karyawan->jenis_kelamin ?: optional($recruitment)->jenis_kelamin));
        $gender = strtoupper($genderRaw);
        $genderLabel = [
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            'M' => 'Laki-laki',
            'F' => 'Perempuan',
            'MALE' => 'Laki-laki',
            'FEMALE' => 'Perempuan',
            'LAKI-LAKI' => 'Laki-laki',
            'PEREMPUAN' => 'Perempuan',
        ];

        return [
            'logo' => $view['logo'],
            'noDokumen' => $noDokumen,
            'namaNik' => $nama . ' / ' . ($nik !== '' ? $nik : '-'),
            'noKtp' => $view['noKtp'],
            'jenisKelamin' => $genderLabel[$gender] ?? ($genderRaw !== '' ? $genderRaw : '-'),
            'tempatTanggalLahir' => $view['tempatTanggalLahir'],
            'alamat' => $view['alamatLengkap'],
            'namaKaryawan' => $nama,
            'nikKaryawan' => $nik !== '' ? $nik : '-',
            'tanggalPengesahan' => $view['tanggalPengesahan'],
        ];
    }

    private function buildSpnyViewData(array $data, string $noDokumen): array
    {
        $view = $this->buildPkkpViewData($data, $noDokumen);
        $karyawan = $data['karyawan'];
        $nik = trim((string) ($karyawan->nik_karyawan ?: ''));
        $posisi = $this->jabatanDokumen($karyawan);
        $divisi = ($karyawan->getAttributes()['department'] ?? null)
            ?: (optional($karyawan->getRelation('department'))->nama_divisi ?: null);
        $posisiDivisi = $posisi && $divisi ? $posisi . ' / ' . $divisi : ($posisi ?: $divisi);

        return [
            'noDokumen' => $noDokumen,
            'namaNik' => $view['namaLengkap'] . ' / ' . ($nik !== '' ? $nik : '-'),
            'tempatTanggalLahir' => $view['tempatTanggalLahir'],
            'noKtp' => $view['noKtp'],
            'posisiDivisi' => $posisiDivisi ?: '-',
            'posisi' => $posisi ?: '-',
            'divisi' => $divisi ?: '-',
            'alamat' => $view['alamatLengkap'],
            'tanggalPengesahan' => $view['tanggalPengesahan'],
            'namaKaryawan' => $view['namaKaryawan'],
            'jabatanKaryawan' => $posisi ?: '-',
        ];
    }

    private function buildNdaViewData(array $data, string $noDokumen): array
    {
        $view = $this->buildSpkbViewData($data, $noDokumen);
        $karyawan = $data['karyawan'];
        $penanggungJawab = $data['penanggung_jawab'];
        $jabatanPj = $penanggungJawab['jabatan'] ?? null;
        if (is_object($jabatanPj)) {
            $jabatanPj = $jabatanPj->nama_jabatan ?? null;
        }
        $jabatanPj = $this->camelCaseTeks($jabatanPj);
        $posisi = $this->jabatanDokumen($karyawan);
        $mulaiKontrak = $karyawan->tgl_mulai_kerja ? Carbon::parse($karyawan->tgl_mulai_kerja)->startOfDay() : null;
        $akhirKontrak = $karyawan->tgl_berakhir_kontrak ? Carbon::parse($karyawan->tgl_berakhir_kontrak)->startOfDay() : null;
        $jangkaWaktuNonKompetisi = '-';
        if ($mulaiKontrak && $akhirKontrak && $akhirKontrak->gt($mulaiKontrak)) {
            $tahun = max(1, (int) round($mulaiKontrak->diffInMonths($akhirKontrak) / 12));
            $jangkaWaktuNonKompetisi = $tahun . ' (' . $this->terbilang($tahun) . ')';
        }

        return [
            'noDokumen' => $noDokumen,
            'hariPerjanjian' => $this->namaHariIndonesia(Carbon::now()),
            'tanggalPerjanjian' => $view['tanggalPengesahan'],
            'namaPenanggungJawab' => $this->camelCaseTeks($penanggungJawab['nama'] ?? null) ?: '-',
            'jabatanPenanggungJawab' => $jabatanPj ?: '-',
            'namaNik' => $view['namaNik'],
            'noKtp' => $view['noKtp'],
            'tempatTanggalLahir' => $view['tempatTanggalLahir'],
            'jenisKelamin' => $view['jenisKelamin'],
            'alamat' => $view['alamat'],
            'namaKaryawan' => $view['namaKaryawan'],
            'jabatanKaryawan' => $posisi ?: '-',
            'jangkaWaktu' => '2 (dua)',
            'jangkaWaktuNonKompetisi' => $jangkaWaktuNonKompetisi,
        ];
    }

    private function buildSprjViewData(array $data, string $noDokumen, string $noPengesahan): array
    {
        $karyawan = $data['karyawan'];
        $recruitment = $data['recruitment'];
        $salary = $data['salary'];
        $identity = $this->buildSpkbViewData($data, $noDokumen);
        $penanggungJawab = $data['penanggung_jawab'];
        $jabatanPenanggungJawab = $penanggungJawab['jabatan'] ?? null;
        if (is_object($jabatanPenanggungJawab)) {
            $jabatanPenanggungJawab = $jabatanPenanggungJawab->nama_jabatan ?? null;
        }
        $jabatanPenanggungJawab = $this->camelCaseTeks($jabatanPenanggungJawab);
        $period = $this->sprjAgreementPeriod($karyawan, $salary);
        $startDate = $period['start'];
        $endDate = $period['end'];
        $gajiPokok = (float) optional($salary)->gaji_pokok;
        $tunjangan = (float) optional($salary)->tunjangan_kerja;
        $bpjsKes = (float) optional($data['bpjs_kesehatan'])->nominal_potongan_karyawan;
        $bpjsTk = (float) optional($data['bpjs_tk'])->nominal_potongan_karyawan;
        $pph21 = (float) optional($data['pph_21'])->pajak_bulanan;
        $pencadangan = (float) optional($data['pencadangan_upah'])->nominal;
        $totalPotongan = $bpjsKes + $bpjsTk + $pph21 + $pencadangan;
        $periodePencadangan = $this->periodePencadangan($data['pencadangan_upah'], $startDate, $endDate);
        $pengembalianPencadangan = $this->jadwalPengembalianPencadangan($data['pencadangan_upah'], $endDate);

        return [
            'noDokumen' => $noDokumen,
            'noPengesahan' => $noPengesahan,
            'karyawan' => $karyawan,
            'recruitment' => $recruitment,
            'penanggungJawab' => $penanggungJawab,
            'namaPenanggungJawab' => $this->camelCaseTeks($penanggungJawab['nama'] ?? null) ?: '-',
            'jabatanPenanggungJawab' => $jabatanPenanggungJawab ?: '-',
            'namaKaryawan' => $identity['namaKaryawan'],
            'jabatanKaryawan' => $this->jabatanDokumen($karyawan) ?: '-',
            'namaNik' => $identity['namaNik'],
            'noKtp' => $identity['noKtp'],
            'tempatTanggalLahir' => $identity['tempatTanggalLahir'],
            'jenisKelamin' => $identity['jenisKelamin'],
            'alamatKaryawan' => $identity['alamat'],
            'emailKaryawan' => $karyawan->email_pribadi
                ?: ($karyawan->email ?: (optional($recruitment)->email ?: '-')),
            'salary' => $salary,
            'rekening' => $data['rekening'],
            'pencadangan' => $data['pencadangan_upah'],
            'tanggalPerjanjian' => $this->formatTanggalIndonesia(Carbon::now()),
            'hariPerjanjian' => $this->namaHariIndonesia(Carbon::now()),
            'tanggalMulai' => $startDate ? $this->formatTanggalIndonesia($startDate) : '-',
            'tanggalSelesai' => $endDate ? $this->formatTanggalIndonesia($endDate) : '-',
            'jumlahJangkaWaktu' => $period['jumlah'],
            'jumlahJangkaWaktuTerbilang' => $period['jumlah'] ? $this->terbilang((int) $period['jumlah']) : '-',
            'satuanJangkaWaktu' => $period['satuan'] === 'tahun' ? 'tahun' : 'bulan',
            'gajiPokok' => $gajiPokok,
            'tunjangan' => $tunjangan,
            'bpjsKes' => $bpjsKes,
            'bpjsTk' => $bpjsTk,
            'sumberPotonganBpjsTk' => $this->labelSumberPotonganBpjsTk(
                optional($data['bpjs_tk'])->sumber_potongan
            ),
            'pph21' => $pph21,
            'totalPotongan' => $totalPotongan,
            'totalUpah' => max(0, $gajiPokok + $tunjangan - $totalPotongan),
            'tanggalPencadanganMulai' => $periodePencadangan['mulai'],
            'tanggalPencadanganSelesai' => $periodePencadangan['selesai'],
            'bulanPenggajianPencadangan' => $pengembalianPencadangan['penggajian'],
            'bulanDiterimaPencadangan' => $pengembalianPencadangan['diterima'],
            'hariRaya' => $this->hariRayaKeagamaan($karyawan, $data['recruitment'], $data['salary'] ?? null),
            'metodePembayaran' => $this->labelMetodePembayaran(optional($salary)->metode_pembayaran_gaji),
            'rekeningGaji' => $this->barisRekeningGaji($data['rekening'] ?? null),
            'jumlahHariKerja' => $this->angkaPayroll(optional($salary)->jumlah_hari_kerja, 1),
            'jadwalPenggajian' => $this->angkaPayroll(optional($salary)->jadwal_penggajian, 0),
        ];
    }

    private function labelSumberPotonganBpjsTk($sumber): string
    {
        $labels = [
            'gaji_pokok' => 'Gaji Pokok',
            'umr_kota' => 'UMR Kota',
            'lainnya' => 'Lainnya',
        ];

        return $labels[strtolower(trim((string) $sumber))] ?? 'Gaji Pokok';
    }

    private function labelMetodePembayaran($metode): string
    {
        $metode = strtolower(trim((string) $metode));
        if ($metode === 'tunai') {
            return 'tunai (cash)';
        }
        if ($metode === 'transfer') {
            return 'transfer';
        }

        return 'transfer';
    }

    private function barisRekeningGaji($rekening): array
    {
        $fields = [
            'Nama Bank' => 'nama_bank',
            'Nomor Rekening' => 'no_rekening',
            'Cabang Bank' => 'cabang_bank',
            'Nama Pemilik Rekening' => 'nama_pemilik_rekening',
        ];
        $rows = [];
        foreach ($fields as $label => $field) {
            $value = trim((string) optional($rekening)->{$field});
            if ($value !== '') {
                $rows[] = ['label' => $label, 'value' => $value];
            }
        }

        return $rows;
    }

    private function angkaPayroll($value, int $min): ?int
    {
        if ($value === null || trim((string) $value) === '' || !is_numeric($value)) {
            return null;
        }

        $number = (int) $value;
        return $number >= $min ? $number : null;
    }

    private function hariRayaKeagamaan($karyawan, $recruitment, $salary = null): string
    {
        $jadwal = trim((string) optional($salary)->jadwal_pembayaran_thr);
        if ($jadwal !== '') {
            return $jadwal;
        }

        $agama = trim((string) ($karyawan->agama ?? ''));
        if ($agama === '' && $recruitment) {
            $recruitment->loadMissing('candidateProfile');
            $agama = trim((string) (
                ($recruitment->agama ?? null)
                ?: optional($recruitment->candidateProfile)->agama
            ));
        }

        $map = [
            'islam' => 'Idul Fitri',
            'muslim' => 'Idul Fitri',
            'protestan' => 'Natal',
            'kristen' => 'Natal',
            'kristen protestan' => 'Natal',
            'katolik' => 'Natal',
            'kristen katolik' => 'Natal',
            'hindu' => 'Nyepi',
            'buddha' => 'Waisak',
            'budha' => 'Waisak',
            'konghucu' => 'Imlek',
            'khonghucu' => 'Imlek',
        ];

        return $map[strtolower($agama)] ?? '-';
    }

    private function periodePencadangan($pencadangan, ?Carbon $contractStart, ?Carbon $contractEnd): array
    {
        $empty = ['mulai' => '-', 'selesai' => '-'];
        if (!$pencadangan || (float) $pencadangan->nominal <= 0) {
            return $empty;
        }

        $start = null;
        if (!empty($pencadangan->bulan_efektif)) {
            $start = Carbon::parse($pencadangan->bulan_efektif)->startOfMonth();
        } elseif ($contractStart) {
            $start = $contractStart->copy();
        }

        $tenor = (int) $pencadangan->tenor;
        if ($start && $tenor > 0) {
            $end = $start->copy()->addMonths($tenor)->subDay();
        } else {
            $end = $contractEnd ? $contractEnd->copy() : null;
        }

        return [
            'mulai' => $start ? $this->formatTanggalIndonesia($start) : '-',
            'selesai' => $end ? $this->formatTanggalIndonesia($end) : '-',
        ];
    }

    private function jadwalPengembalianPencadangan($pencadangan, ?Carbon $contractEnd): array
    {
        $empty = ['penggajian' => '-', 'diterima' => '-'];
        if (!$pencadangan || (float) $pencadangan->nominal <= 0) {
            return $empty;
        }

        $tenor = max(1, (int) $pencadangan->tenor);
        $tipe = strtolower(trim((string) ($pencadangan->tipe_pengembalian ?? 'setelah_potongan')));
        $payrollMonth = null;

        if ($tipe === 'setelah_kontrak' && $contractEnd) {
            $payrollMonth = $contractEnd->copy()->startOfMonth();
        } elseif (!empty($pencadangan->bulan_efektif)) {
            $payrollMonth = Carbon::parse($pencadangan->bulan_efektif)
                ->startOfMonth()
                ->addMonths($tenor - 1);
        }

        if (!$payrollMonth) {
            return $empty;
        }

        return [
            'penggajian' => $this->formatBulanIndonesia($payrollMonth),
            'diterima' => $this->formatBulanIndonesia($payrollMonth->copy()->addMonth()),
        ];
    }

    private function formatBulanIndonesia(Carbon $date): string
    {
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $months[$date->month] . ' ' . $date->year;
    }

    private function documentResult(EmployeeDocument $document): array
    {
        return [
            'id' => (int) $document->id,
            'jenis_dokumen' => $document->document_type,
            'no_dokumen' => $document->document_number,
            'nama_file' => $document->file_name,
            'path_file' => $document->file_path,
            'mime_type' => $document->mime_type,
            'ukuran_file' => (int) ($document->file_size ?? 0),
            'status' => $document->status,
        ];
    }

    private function jabatanDokumen($karyawan): string
    {
        return $this->camelCaseTeks(
            ($karyawan->getAttributes()['jabatan'] ?? null)
            ?: optional($karyawan->getRelation('jabatan'))->nama_jabatan
        );
    }

    private function alamatDokumen($karyawan, $profile): string
    {
        $alamat = trim((string) (
            ($karyawan->alamat ?? null)
            ?: (optional($profile)->alamat_domisili ?: optional($profile)->alamat_ktp)
        ));
        $kota = $this->camelCaseTeks(
            ($karyawan->kota ?? null)
            ?: (optional($profile)->kota_domisili ?: optional($profile)->kota_ktp)
        );
        $provinsi = $this->camelCaseTeks(
            ($karyawan->provinsi ?? null)
            ?: (optional($profile)->provinsi_domisili ?: optional($profile)->provinsi_ktp)
        );

        if ($kota !== '') {
            $alamat = $this->sisipkanBagianAlamat($alamat, $kota);
        }
        if ($provinsi !== '') {
            $alamat = $this->sisipkanBagianAlamat($alamat, $provinsi);
        }

        return $alamat !== '' ? $alamat : '-';
    }

    private function sisipkanBagianAlamat(string $alamat, string $bagian): string
    {
        if ($alamat === '') {
            return $bagian;
        }

        $pattern = '/(^|[^\p{L}\p{N}])' . preg_quote($bagian, '/') . '(?![\p{L}\p{N}])/iu';
        if (!preg_match($pattern, $alamat)) {
            return $alamat . ', ' . $bagian;
        }

        $replaced = preg_replace_callback(
            $pattern,
            function (array $match) use ($bagian): string {
                return $match[1] . $bagian;
            },
            $alamat,
            1
        );

        return $replaced === null ? $alamat : $replaced;
    }

    private function camelCaseTeks($value): string
    {
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return $text;
        }

        $text = preg_replace('/\s+/u', ' ', $text);
        $lower = mb_strtolower($text, 'UTF-8');
        $titled = preg_replace_callback(
            '/\p{L}+/u',
            function (array $match): string {
                $word = $match[0];
                $upper = mb_strtoupper($word, 'UTF-8');
                if (in_array($upper, self::SINGKATAN_TETAP, true)) {
                    return $upper;
                }

                return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_substr($word, 1, null, 'UTF-8');
            },
            $lower
        );
        $titled = preg_replace('/\bDi Yogyakarta\b/u', 'DI Yogyakarta', (string) $titled);

        return $titled === null || $titled === '' ? $text : $titled;
    }

    private function formatTanggalIndonesia(Carbon $date): string
    {
        $months = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return $date->day . ' ' . $months[$date->month] . ' ' . $date->year;
    }

    private function namaHariIndonesia(Carbon $date): string
    {
        $days = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        return $days[$date->format('l')] ?? $date->format('l');
    }

    private function sprjAgreementPeriod($karyawan, $salary): array
    {
        $startDate = !empty($karyawan->tgl_mulai_kerja) ? Carbon::parse($karyawan->tgl_mulai_kerja) : null;
        $unit = optional($salary)->satuan_jangka_waktu;
        $amount = (int) optional($salary)->jumlah_jangka_waktu;

        if ($startDate && in_array($unit, ['bulan', 'tahun'], true) && $amount > 0) {
            $endDate = $unit === 'tahun'
                ? $startDate->copy()->addYears($amount)
                : $startDate->copy()->addMonths($amount);

            return [
                'start' => $startDate,
                'end' => $endDate,
                'jumlah' => $amount,
                'satuan' => $unit,
            ];
        }

        $endDate = !empty($karyawan->tgl_berakhir_kontrak) ? Carbon::parse($karyawan->tgl_berakhir_kontrak) : null;
        $months = $startDate && $endDate ? max(1, (int) $startDate->diffInMonths($endDate)) : null;

        return [
            'start' => $startDate,
            'end' => $endDate,
            'jumlah' => $months,
            'satuan' => 'bulan',
        ];
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
            return $this->terbilang($number - 10) . ' belas';
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