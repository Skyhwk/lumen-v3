<?php

namespace App\Services;

use App\Models\EmployeeDocumentSequence;
use App\Models\MasterCabang;
use App\Models\SuratPeringatanDisipliner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use RuntimeException;

class SuratPeringatanDocumentService
{
    private const STORAGE_DIR = 'dokumen/disipliner/surat-peringatan';

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

    public function generateAndStorePdf(SuratPeringatanDisipliner $letter, object $detailRow): SuratPeringatanDisipliner
    {
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

        $letter->nama_berkas = $fileName;
        $letter->file_path = $relativePath;
        $letter->file_size = filesize($fullPath) ?: null;
        $letter->save();

        return $letter->fresh();
    }

    public function readPdfBinary(SuratPeringatanDisipliner $letter): string
    {
        if (!$letter->file_path) {
            throw new RuntimeException('File surat peringatan belum tersedia.');
        }

        $fullPath = public_path($letter->file_path);
        if (!is_file($fullPath)) {
            throw new RuntimeException('File surat peringatan tidak ditemukan di server.');
        }

        return file_get_contents($fullPath);
    }

    public function renderPdf(SuratPeringatanDisipliner $letter, object $detailRow): string
    {
        Carbon::setLocale('id');

        $issueDate = $letter->tanggal_terbit
            ? Carbon::parse($letter->tanggal_terbit)->locale('id')
            : Carbon::now()->locale('id');

        $incidentDate = $letter->tanggal_kejadian
            ? Carbon::parse($letter->tanggal_kejadian)->locale('id')
            : null;

        $previousReference = null;
        if ($letter->suratSebelumnya) {
            $previousReference = trim(
                $letter->suratSebelumnya->jenis_peringatan . ' nomor ' . $letter->suratSebelumnya->nomor_surat
            );
        }

        $html = view('disipliner.surat-peringatan', [
            'warningTypeLabel' => $letter->jenis_peringatan,
            'documentNumber' => $letter->nomor_surat,
            'employeeName' => $detailRow->nama_lengkap ?? '-',
            'employeeNik' => $detailRow->nik_karyawan ?? '-',
            'divisionName' => $detailRow->nama_divisi ?? ($detailRow->department ?? '-'),
            'positionName' => $detailRow->jabatan ?? '-',
            'incidentDateLabel' => $incidentDate ? $incidentDate->translatedFormat('d F Y') : '-',
            'violationCategory' => trim((string) $letter->jenis_pelanggaran) ?: '-',
            'violationDescription' => trim((string) $letter->uraian_pelanggaran) ?: '<p>-</p>',
            'previousReference' => $previousReference,
            'issueDateLong' => $issueDate->translatedFormat('d F Y'),
            'issueDateShort' => $issueDate->translatedFormat('d F Y'),
            'company' => $this->resolveCompanyHeader(),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'default_font' => 'times',
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
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

    private function resolveCompanyHeader(): array
    {
        $logoPath = file_exists(public_path('isl_logo.png'))
            ? public_path('isl_logo.png')
            : public_path('img/isl_logo.png');

        $cabang = MasterCabang::query()
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        return [
            'logo_path' => $logoPath,
            'name' => 'PT INTI SURYA LABORATORIUM',
            'address' => trim((string) ($cabang->alamat_cabang ?? '')),
        ];
    }
}
