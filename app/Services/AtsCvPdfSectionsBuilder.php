<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AtsCvPdfSectionsBuilder
{
    public function buildProfileCompletionSections($applicant, $cp): string
    {
        if (!$cp && !$applicant) {
            return '';
        }

        $sections = [
            $this->buildEmergencySection($cp),
            $this->buildSkillsSection($applicant),
            $this->buildSupportingSection($cp, $applicant),
            $this->buildDocumentsSection($cp, $applicant),
        ];

        return implode('', array_filter($sections));
    }

    private function buildEmergencySection($cp): string
    {
        if (!$cp) {
            return '';
        }

        $rows = [
            'Emergency Contact Name' => $cp->nama_kontak_darurat ?? null,
            'Emergency Relationship' => $cp->hubungan_kontak_darurat ?? null,
            'Emergency Phone' => $cp->no_telepon_darurat ?? null,
            'Emergency Contact Name (2)' => $cp->nama_kontak_darurat_2 ?? null,
            'Emergency Relationship (2)' => $cp->hubungan_kontak_darurat_2 ?? null,
            'Emergency Phone (2)' => $cp->no_telepon_darurat_2 ?? null,
            'Number of Dependents' => isset($cp->jumlah_tanggungan) && $cp->jumlah_tanggungan !== '' ? (string) $cp->jumlah_tanggungan : null,
            'Profile Agreement' => isset($cp->is_agreed) ? ((int) $cp->is_agreed === 1 ? 'Agreed / Setuju' : 'Not yet agreed') : null,
        ];

        $html = $this->buildInfoTableSection('Emergency & Family Contact', $rows);
        return $html ?: '';
    }

    private function buildSkillsSection($applicant): string
    {
        if (!$applicant) {
            return '';
        }

        $rawSkills = $applicant->skill ?? null;
        if (is_string($rawSkills)) {
            $rawSkills = json_decode($rawSkills, true);
        }
        if (!is_array($rawSkills) || count($rawSkills) === 0) {
            return '';
        }

        $itemsHtml = '';
        foreach ($rawSkills as $skill) {
            if (!is_array($skill)) {
                continue;
            }
            $name = trim((string) ($skill['keahlian'] ?? $skill['skill'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rate = $skill['rate'] ?? null;
            $rateStr = ($rate !== null && $rate !== '') ? " <span style='color:#64748b;'>(Rating: {$this->escape($rate)}/10)</span>" : '';
            $itemsHtml .= "
                <div class='cv-list-item'>
                    <strong>{$this->escape($name)}</strong>{$rateStr}
                </div>";
        }

        if ($itemsHtml === '') {
            return '';
        }

        return "
            <div class='section-title'>Skills &amp; Competencies</div>
            <div class='cv-card-block'>{$itemsHtml}</div>";
    }

    private function buildSupportingSection($cp, $applicant): string
    {
        if (!Schema::hasTable('candidate_supporting_info_answers')) {
            return '';
        }

        $query = DB::table('candidate_supporting_info_answers')
            ->where('is_active', 1)
            ->orderBy('question_category_id')
            ->orderBy('question_id');

        if ($cp && !empty($cp->id)) {
            $query->where('candidate_profile_id', $cp->id);
        } elseif ($applicant && !empty($applicant->id)) {
            $query->where('new_recruitment_id', $applicant->id);
        } else {
            return '';
        }

        $answers = $query->get();
        if ($answers->isEmpty()) {
            return '';
        }

        $itemsHtml = '';
        $currentCategory = null;

        foreach ($answers as $answer) {
            $category = trim((string) ($answer->category_name ?? 'Informasi Pendukung'));
            if ($category !== $currentCategory) {
                $currentCategory = $category;
                $itemsHtml .= "<div class='cv-subsection-title'>{$this->escape($category)}</div>";
            }

            $question = trim((string) ($answer->question_text ?? 'Question'));
            $answerText = trim((string) ($answer->answer_text ?? '-'));

            $itemsHtml .= "
                <div class='cv-list-item'>
                    <div class='cv-question'>{$this->escape($question)}</div>
                    <div class='cv-answer'>{$this->escape($answerText)}</div>
                </div>";
        }

        return "
            <div class='section-title'>Supporting Information</div>
            <div class='cv-card-block'>{$itemsHtml}</div>";
    }

    private function buildDocumentsSection($cp, $applicant): string
    {
        if (!Schema::hasTable('candidate_documents')) {
            return '';
        }

        $query = DB::table('candidate_documents')
            ->where('is_active', 1)
            ->orderBy('jenis_dokumen');

        if ($cp && !empty($cp->id)) {
            $query->where('candidate_profile_id', $cp->id);
        } elseif ($applicant && !empty($applicant->id)) {
            $query->where('new_recruitment_id', $applicant->id);
        } else {
            return '';
        }

        $documents = $query->get();
        if ($documents->isEmpty()) {
            return '';
        }

        $rowsHtml = '';
        foreach (array_chunk($documents->all(), 3) as $rowDocs) {
            $cellsHtml = '';
            foreach ($rowDocs as $doc) {
                $cellsHtml .= $this->buildDocumentCell($doc);
            }

            $missingCells = 3 - count($rowDocs);
            for ($i = 0; $i < $missingCells; $i++) {
                $cellsHtml .= "<td class='cv-doc-cell cv-doc-cell-empty'></td>";
            }

            $rowsHtml .= "<tr>{$cellsHtml}</tr>";
        }

        return "
            <div class='section-title'>Document Attachments</div>
            <table class='cv-doc-grid'>{$rowsHtml}</table>";
    }

    private function buildDocumentCell($doc): string
    {
        $type = $this->escape($doc->jenis_dokumen ?? 'Dokumen');
        $previewHtml = $this->buildDocumentPreviewContent($doc);
        $note = trim((string) ($doc->catatan ?? ''));
        $noteHtml = $note !== '' ? "<div class='cv-doc-note'>{$this->escape($note)}</div>" : '';

        return "
            <td class='cv-doc-cell'>
                <div class='cv-doc-type'>{$type}</div>
                {$previewHtml}
                {$noteHtml}
            </td>";
    }

    private function buildDocumentPreviewContent($doc): string
    {
        $absolutePath = $this->resolveDocumentAbsolutePath($doc);
        if (!$absolutePath) {
            return "<div class='cv-doc-placeholder'>Preview unavailable</div>";
        }

        $mime = strtolower((string) ($doc->mime_type ?? ''));
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = strtolower((string) mime_content_type($absolutePath));
        }
        $mime = $mime ?: 'application/octet-stream';

        if (strpos($mime, 'image/') === 0) {
            $dataUri = $this->buildCompactImageDataUri($absolutePath, $mime);
            if (!$dataUri) {
                return "<div class='cv-doc-placeholder'>Preview unavailable</div>";
            }

            return "<img src='{$dataUri}' class='cv-doc-thumb' alt='Document preview'>";
        }

        if ($mime === 'application/pdf') {
            return "<div class='cv-doc-placeholder cv-doc-placeholder-pdf'>PDF Document</div>";
        }

        return "<div class='cv-doc-placeholder'>Attachment</div>";
    }

    private function resolveDocumentAbsolutePath($doc): ?string
    {
        $relativePath = ltrim(str_replace(['\\'], '/', (string) ($doc->path_file ?? '')), '/');
        $candidates = [];

        if ($relativePath !== '') {
            $candidates[] = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        }

        $baseName = basename($relativePath !== '' ? $relativePath : (string) ($doc->nama_file ?? ''));
        if ($baseName !== '' && $baseName !== '.' && $baseName !== '/') {
            $candidates[] = public_path('recruitment' . DIRECTORY_SEPARATOR . 'candidate-documents' . DIRECTORY_SEPARATOR . $baseName);
        }

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function buildCompactImageDataUri(string $absolutePath, string $mime, int $maxWidth = 240, int $maxHeight = 170): ?string
    {
        $binary = @file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (!function_exists('imagecreatefromstring')) {
            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        }

        $source = @imagecreatefromstring($binary);
        if (!$source) {
            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);
            return null;
        }

        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        $background = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $background);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
        }

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        ob_start();
        imagejpeg($target, null, 78);
        $output = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        if ($output === false || $output === '') {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($output);
    }

    private function buildInfoTableSection(string $title, array $rows): string
    {
        $rowsHtml = '';
        foreach ($rows as $label => $value) {
            $value = trim((string) ($value ?? ''));
            if ($value === '' || $value === '-') {
                continue;
            }
            $rowsHtml .= "
                <tr>
                    <td class='info-label'>{$this->escape($label)}</td>
                    <td class='info-value'>{$this->escape($value)}</td>
                </tr>";
        }

        if ($rowsHtml === '') {
            return '';
        }

        return "
            <div class='section-title'>{$this->escape($title)}</div>
            <table class='info-table'>{$rowsHtml}</table>";
    }

    private function formatBytes($bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '-';
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 2) . ' MB';
    }

    private function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
