<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KaryawanArsipDokumenService
{
    public function archiveDirectory($karyawanId)
    {
        return public_path('dokumen/arsip-karyawan/' . $karyawanId);
    }

    public function archiveRelativePath($karyawanId, $fileName)
    {
        return 'dokumen/arsip-karyawan/' . $karyawanId . '/' . $fileName;
    }

    public function ensureArchiveDirectory($karyawanId)
    {
        $directory = $this->archiveDirectory($karyawanId);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    public function listByKaryawan($karyawanId)
    {
        if (!Schema::hasTable('karyawan_dokumen_arsip')) {
            return collect();
        }

        return DB::table('karyawan_dokumen_arsip')
            ->where('karyawan_id', $karyawanId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $row->is_image = $this->isImageMime($row->mime_type, $row->path_file);
                return $row;
            });
    }

    public function migrateFromCandidateDocuments($recruitmentId, $karyawanId, $createdBy = null)
    {
        if (!Schema::hasTable('candidate_documents') || !Schema::hasTable('karyawan_dokumen_arsip')) {
            return 0;
        }

        $documents = DB::table('candidate_documents')
            ->where('new_recruitment_id', $recruitmentId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $migrated = 0;
        $directory = $this->ensureArchiveDirectory($karyawanId);

        foreach ($documents as $doc) {
            $sourcePath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $doc->path_file));
            if (!is_file($sourcePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($doc->nama_file ?: $doc->path_file, PATHINFO_EXTENSION));
            if (!$extension) {
                $extension = 'bin';
            }

            $safeType = preg_replace('/[^a-z0-9]+/i', '-', strtolower($doc->jenis_dokumen));
            $fileName = 'arsip-' . $karyawanId . '-' . trim($safeType, '-') . '-' . time() . '-' . substr(md5($doc->id . $doc->path_file), 0, 6) . '.' . $extension;
            $destinationPath = $directory . DIRECTORY_SEPARATOR . $fileName;
            $relativePath = $this->archiveRelativePath($karyawanId, $fileName);

            if (!$this->moveFile($sourcePath, $destinationPath)) {
                continue;
            }

            DB::table('karyawan_dokumen_arsip')->insert([
                'karyawan_id' => $karyawanId,
                'jenis_dokumen' => $doc->jenis_dokumen,
                'nama_file' => $doc->nama_file ?: $fileName,
                'path_file' => $relativePath,
                'mime_type' => $doc->mime_type,
                'ukuran_file' => $doc->ukuran_file ?: filesize($destinationPath),
                'sumber' => 'migrasi_kandidat',
                'catatan' => $doc->catatan,
                'is_active' => 1,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('candidate_documents')->where('id', $doc->id)->update([
                'is_active' => 0,
                'catatan' => trim(($doc->catatan ? $doc->catatan . ' | ' : '') . 'Dipindahkan ke arsip karyawan #' . $karyawanId),
                'updated_at' => $now,
            ]);

            $migrated++;
        }

        return $migrated;
    }

    public function storeUploadedFile($karyawanId, UploadedFile $file, $jenisDokumen, $createdBy = null, $catatan = null)
    {
        if (!Schema::hasTable('karyawan_dokumen_arsip')) {
            throw new \RuntimeException('Tabel arsip dokumen karyawan belum tersedia.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw new \RuntimeException('Dokumen hanya menerima PDF, JPG, atau PNG.');
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \RuntimeException('Ukuran dokumen maksimal 5 MB.');
        }

        $safeType = preg_replace('/[^a-z0-9]+/i', '-', strtolower($jenisDokumen ?: 'dokumen'));
        $fileName = 'upload-' . $karyawanId . '-' . trim($safeType, '-') . '-' . time() . '-' . substr(md5($file->getClientOriginalName() . microtime(true)), 0, 6) . '.' . $extension;
        $directory = $this->ensureArchiveDirectory($karyawanId);
        $file->move($directory, $fileName);

        $relativePath = $this->archiveRelativePath($karyawanId, $fileName);
        $now = date('Y-m-d H:i:s');

        $id = DB::table('karyawan_dokumen_arsip')->insertGetId([
            'karyawan_id' => $karyawanId,
            'jenis_dokumen' => strtoupper(trim($jenisDokumen)),
            'nama_file' => $file->getClientOriginalName(),
            'path_file' => $relativePath,
            'mime_type' => $file->getClientMimeType(),
            'ukuran_file' => filesize($directory . DIRECTORY_SEPARATOR . $fileName),
            'sumber' => 'upload_manual',
            'catatan' => $catatan,
            'is_active' => 1,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('karyawan_dokumen_arsip')->where('id', $id)->first();
        $row->is_image = $this->isImageMime($row->mime_type, $row->path_file);

        return $row;
    }

    public function deleteDocument($id, $karyawanId = null)
    {
        if (!Schema::hasTable('karyawan_dokumen_arsip')) {
            return false;
        }

        $query = DB::table('karyawan_dokumen_arsip')->where('id', $id);
        if ($karyawanId) {
            $query->where('karyawan_id', $karyawanId);
        }

        $doc = $query->first();
        if (!$doc) {
            return false;
        }

        $absolutePath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $doc->path_file));
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        DB::table('karyawan_dokumen_arsip')->where('id', $id)->update([
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function moveFile($sourcePath, $destinationPath)
    {
        if (@rename($sourcePath, $destinationPath)) {
            return true;
        }

        if (@copy($sourcePath, $destinationPath)) {
            @unlink($sourcePath);
            return true;
        }

        return false;
    }

    private function isImageMime($mimeType, $pathFile)
    {
        if ($mimeType && stripos($mimeType, 'image/') === 0) {
            return true;
        }

        $extension = strtolower(pathinfo($pathFile, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }
}
