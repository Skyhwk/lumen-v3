<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CandidateDocumentAttachmentService
{
    public function listAttachmentLabels(int $recruitmentId): array
    {
        $labels = [];

        foreach ($this->fetchDocuments($recruitmentId) as $doc) {
            $labels[] = $this->mapDocumentToLabel($doc);
        }

        return $labels;
    }

    public function buildSendEmailAttachments(int $recruitmentId): array
    {
        $attachments = [];

        foreach ($this->fetchDocuments($recruitmentId) as $doc) {
            $relativePath = $this->normalizeRelativePath((string) ($doc->path_file ?? ''));
            if ($relativePath === '') {
                continue;
            }

            $fullPath = public_path($relativePath);
            if (!is_file($fullPath)) {
                continue;
            }

            $attachments[] = [
                'path' => $relativePath,
                'name' => $this->buildAttachmentName($doc),
            ];
        }

        return $attachments;
    }

    private function fetchDocuments(int $recruitmentId)
    {
        if (!Schema::hasTable('candidate_documents')) {
            return collect();
        }

        $query = DB::table('candidate_documents')
            ->where('is_active', 1)
            ->orderBy('jenis_dokumen')
            ->orderBy('id');

        $documents = (clone $query)
            ->where('new_recruitment_id', $recruitmentId)
            ->get();

        if ($documents->isNotEmpty()) {
            return $documents;
        }

        $profileId = DB::table('candidate_profiles')
            ->where('new_recruitment_id', $recruitmentId)
            ->value('id');

        if (!$profileId) {
            return collect();
        }

        return (clone $query)
            ->where('candidate_profile_id', $profileId)
            ->get();
    }

    private function mapDocumentToLabel($doc): array
    {
        $jenis = trim((string) ($doc->jenis_dokumen ?? 'Dokumen'));
        $namaFile = trim((string) ($doc->nama_file ?? ''));

        return [
            'name' => $namaFile !== '' ? $jenis . ' — ' . $namaFile : $jenis,
            'jenis_dokumen' => $jenis,
            'nama_file' => $namaFile,
        ];
    }

    private function buildAttachmentName($doc): string
    {
        $jenis = $this->sanitizeFilenamePart((string) ($doc->jenis_dokumen ?? 'Dokumen'));
        $namaFile = trim((string) ($doc->nama_file ?? ''));

        if ($namaFile === '') {
            $basename = basename($this->normalizeRelativePath((string) ($doc->path_file ?? '')));
            $namaFile = $basename !== '' ? $basename : 'dokumen';
        }

        return $jenis . '_' . $namaFile;
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = ltrim($normalized, '/');

        if (strpos($normalized, 'public/') === 0) {
            $normalized = substr($normalized, 7);
        }

        return $normalized;
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?: 'Dokumen';
        $value = trim($value, '_');

        return $value !== '' ? substr($value, 0, 80) : 'Dokumen';
    }
}
