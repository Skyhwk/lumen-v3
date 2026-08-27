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
        foreach ($documents as $doc) {
            $type = $this->escape($doc->jenis_dokumen ?? '-');
            $fileName = $this->escape($doc->nama_file ?? '-');
            $size = $this->formatBytes($doc->ukuran_file ?? 0);
            $note = trim((string) ($doc->catatan ?? ''));
            $previewHtml = $this->buildDocumentPreview($doc);

            $rowsHtml .= "
                <tr>
                    <td class='info-label'>{$type}</td>
                    <td class='info-value'>
                        <strong>{$fileName}</strong><br>
                        <span style='font-size:10px;color:#64748b;'>Size: {$size}</span>
                        " . ($note !== '' ? "<br><span style='font-size:10px;color:#475569;'>Note: {$this->escape($note)}</span>" : '') . "
                        {$previewHtml}
                    </td>
                </tr>";
        }

        return "
            <div class='section-title'>Document Attachments</div>
            <table class='info-table'>{$rowsHtml}</table>";
    }

    private function buildDocumentPreview($doc): string
    {
        $relativePath = ltrim(str_replace(['\\'], '/', (string) ($doc->path_file ?? '')), '/');
        if ($relativePath === '') {
            return '';
        }

        $absolutePath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return "<div class='cv-doc-note'>File stored in ATS system.</div>";
        }

        $mime = $doc->mime_type ?? (function_exists('mime_content_type') ? mime_content_type($absolutePath) : null);
        $mime = $mime ?: 'application/octet-stream';

        if (stripos($mime, 'image/') === 0) {
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absolutePath));
            return "
                <div class='cv-doc-preview-wrap'>
                    <div class='cv-doc-note'>Preview:</div>
                    <img src='{$dataUri}' class='cv-doc-preview-image' alt='Document preview'>
                </div>";
        }

        if ($mime === 'application/pdf') {
            return "<div class='cv-doc-note'>PDF attachment available in ATS system.</div>";
        }

        return "<div class='cv-doc-note'>Attachment type: {$this->escape($mime)}</div>";
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
