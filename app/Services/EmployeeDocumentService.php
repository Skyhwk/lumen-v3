<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class EmployeeDocumentService
{
    public function storageDirectory($karyawanId)
    {
        return public_path('dokumen/dokumen-karyawan/' . $karyawanId);
    }

    public function relativePath($karyawanId, $fileName)
    {
        return 'dokumen/dokumen-karyawan/' . $karyawanId . '/' . $fileName;
    }

    public function ensureDirectory($karyawanId)
    {
        $directory = $this->storageDirectory($karyawanId);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    public function documentTypes()
    {
        if (!Schema::hasTable('employee_documents')) {
            return [];
        }

        $fromDocs = DB::table('employee_documents')
            ->where('is_active', 1)
            ->whereNotNull('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');

        $fromSeq = collect();
        if (Schema::hasTable('employee_document_sequences')) {
            $fromSeq = DB::table('employee_document_sequences')
                ->whereNotNull('document_type')
                ->distinct()
                ->orderBy('document_type')
                ->pluck('document_type');
        }

        return $fromDocs->merge($fromSeq)->filter()->unique()->values()->all();
    }

    public function listByKaryawan($karyawanId)
    {
        if (!Schema::hasTable('employee_documents')) {
            return collect();
        }

        return DB::table('employee_documents')
            ->where('karyawan_id', $karyawanId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $row->jenis_dokumen = $row->document_type;
                $row->nama_file = $row->file_name;
                $row->path_file = $row->file_path;
                $row->ukuran_file = $row->file_size;
                $row->is_image = $this->isImageMime($row->mime_type, $row->file_path);
                $row->is_pdf = $this->isPdfMime($row->mime_type, $row->file_path);
                return $row;
            });
    }

    public function storeUploadedFile($karyawanId, UploadedFile $file, $documentType, $createdBy = null)
    {
        if (!Schema::hasTable('employee_documents')) {
            throw new \RuntimeException('Tabel employee_documents belum tersedia.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw new \RuntimeException('Dokumen hanya menerima PDF, JPG, atau PNG.');
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \RuntimeException('Ukuran dokumen maksimal 5 MB.');
        }

        $documentType = strtoupper(trim((string) $documentType));
        if ($documentType === '') {
            throw new \RuntimeException('Jenis dokumen wajib diisi.');
        }

        [$documentNumber, $year, $sequenceNumber] = $this->nextDocumentNumber($documentType);

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $safeNumber = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $documentNumber);
        $fileName = $safeNumber . '.' . $extension;
        $directory = $this->ensureDirectory($karyawanId);
        $file->move($directory, $fileName);

        $relativePath = $this->relativePath($karyawanId, $fileName);
        $now = date('Y-m-d H:i:s');

        $id = DB::table('employee_documents')->insertGetId([
            'karyawan_id' => $karyawanId,
            'new_recruitment_id' => null,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'sequence_year' => $year,
            'sequence_number' => $sequenceNumber,
            'file_name' => $originalName ?: $fileName,
            'file_path' => $relativePath,
            'mime_type' => $mimeType ?: ($extension === 'pdf' ? 'application/pdf' : 'image/' . $extension),
            'file_size' => @filesize($directory . DIRECTORY_SEPARATOR . $fileName) ?: null,
            'status' => 'uploaded',
            'is_active' => 1,
            'generated_by' => $createdBy,
            'generated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('employee_documents')->where('id', $id)->first();
        $row->jenis_dokumen = $row->document_type;
        $row->nama_file = $row->file_name;
        $row->path_file = $row->file_path;
        $row->ukuran_file = $row->file_size;
        $row->is_image = $this->isImageMime($row->mime_type, $row->file_path);
        $row->is_pdf = $this->isPdfMime($row->mime_type, $row->file_path);

        return $row;
    }

    public function resolveDownload($id, $karyawanId)
    {
        $row = DB::table('employee_documents')
            ->where('id', $id)
            ->where('karyawan_id', $karyawanId)
            ->where('is_active', 1)
            ->first();

        if (!$row) {
            return null;
        }

        $path = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row->file_path));
        if (!is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => $row->file_name ?: basename($path),
        ];
    }

    public function buildZip($karyawanId, $karyawanName = null)
    {
        $documents = $this->listByKaryawan($karyawanId);
        if ($documents->isEmpty()) {
            throw new \RuntimeException('Tidak ada dokumen untuk diunduh.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'empdocs');
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat arsip zip.');
        }

        $added = 0;
        foreach ($documents as $doc) {
            $path = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $doc->file_path));
            if (!is_file($path)) {
                continue;
            }

            $entry = ($doc->document_number ?: 'dokumen') . '_' . ($doc->file_name ?: basename($path));
            $zip->addFile($path, $entry);
            $added++;
        }

        $zip->close();

        if ($added === 0 || !is_file($zipPath)) {
            @unlink($zipPath);
            throw new \RuntimeException('File dokumen tidak ditemukan di server.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $karyawanName ?: 'karyawan');
        return [
            'path' => $zipPath,
            'name' => 'dokumen-karyawan-' . $safeName . '.zip',
        ];
    }

    public function deleteDocument($id, $karyawanId)
    {
        $row = DB::table('employee_documents')
            ->where('id', $id)
            ->where('karyawan_id', $karyawanId)
            ->where('is_active', 1)
            ->first();

        if (!$row) {
            return false;
        }

        DB::table('employee_documents')->where('id', $id)->update([
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function nextDocumentNumber($documentType)
    {
        $year = (int) date('Y');
        $yy = substr((string) $year, -2);

        return DB::transaction(function () use ($documentType, $year, $yy) {
            $seq = null;
            if (Schema::hasTable('employee_document_sequences')) {
                $seq = DB::table('employee_document_sequences')
                    ->where('document_type', $documentType)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if (!$seq) {
                    $id = DB::table('employee_document_sequences')->insertGetId([
                        'document_type' => $documentType,
                        'prefix' => $documentType . '-',
                        'year' => $year,
                        'last_number' => 0,
                        'padding' => 4,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $seq = DB::table('employee_document_sequences')->where('id', $id)->first();
                }

                $next = (int) $seq->last_number + 1;
                DB::table('employee_document_sequences')->where('id', $seq->id)->update([
                    'last_number' => $next,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $padded = str_pad((string) $next, (int) $seq->padding, '0', STR_PAD_LEFT);
                $prefix = (string) $seq->prefix;
                $number = substr($prefix, -1) === '/'
                    ? $prefix . $yy . '-X/' . $padded
                    : $prefix . $yy . $padded;

                return [$number, $year, $next];
            }

            $next = ((int) DB::table('employee_documents')
                ->where('document_type', $documentType)
                ->where('sequence_year', $year)
                ->max('sequence_number')) + 1;
            $number = $documentType . '-' . $yy . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

            return [$number, $year, $next];
        });
    }

    private function isImageMime($mime, $path)
    {
        $mime = strtolower((string) $mime);
        if (strpos($mime, 'image/') === 0) {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $path);
    }

    private function isPdfMime($mime, $path)
    {
        if (stripos((string) $mime, 'pdf') !== false) {
            return true;
        }

        return (bool) preg_match('/\.pdf$/i', (string) $path);
    }
}
