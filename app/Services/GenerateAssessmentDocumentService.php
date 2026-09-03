<?php

namespace App\Services;

use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use App\Models\NewRecruitment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Output\Destination;

class GenerateAssessmentDocumentService
{
    use BuildsCandidateAssessmentPreview;

    private const OUTPUT_DIR = 'document_assessment';
    private const TEMP_DIR = 'temp/email-attachments/assessment';

    public function generateForRecruitment(int $recruitmentId, bool $persist = true): array
    {
        $context = $this->loadRecruitmentContext($recruitmentId);

        $outputDir = $persist
            ? base_path('public/' . self::OUTPUT_DIR)
            : base_path('public/' . self::TEMP_DIR);

        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $documents = [];
        $failedSessions = [];

        foreach ($context['sessions'] as $session) {
            if (empty($session->result_json)) {
                continue;
            }

            $result = json_decode($session->result_json, true) ?: [];
            $engine = strtolower((string) ($result['engine'] ?? ''));

            try {
                $document = $this->renderSessionPdf(
                    $session,
                    $result,
                    $engine,
                    $context['candidate_name'],
                    $outputDir,
                    $persist
                );

                if ($document) {
                    $documents[] = $document;
                }
            } catch (\Throwable $e) {
                Log::warning('Assessment PDF generation failed for session', [
                    'recruitment_id' => $recruitmentId,
                    'session_id' => $session->id,
                    'category' => $session->category_name,
                    'message' => $e->getMessage(),
                ]);

                $failedSessions[] = [
                    'session_id' => (int) $session->id,
                    'category_name' => trim((string) ($session->category_name ?? 'Assessment')),
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (empty($documents)) {
            throw new \RuntimeException('Tidak ada dokumen PDF yang berhasil dibuat.');
        }

        return [
            'recruitment_id' => $recruitmentId,
            'attempt_id' => (int) $context['attempt']->id,
            'candidate_name' => $context['candidate_name'],
            'persisted' => $persist,
            'documents' => $documents,
            'failed_sessions' => $failedSessions,
        ];
    }

    public function tryGenerateTempAttachments(int $recruitmentId): array
    {
        try {
            return $this->generateForRecruitment($recruitmentId, false);
        } catch (\RuntimeException $e) {
            Log::info('Assessment email attachments skipped', [
                'recruitment_id' => $recruitmentId,
                'message' => $e->getMessage(),
            ]);

            return [
                'recruitment_id' => $recruitmentId,
                'candidate_name' => null,
                'persisted' => false,
                'documents' => [],
                'failed_sessions' => [],
                'skipped_reason' => $e->getMessage(),
            ];
        }
    }

    public function buildSendEmailAttachments(array $documents): array
    {
        $attachments = [];

        foreach ($documents as $document) {
            if (empty($document['path'])) {
                continue;
            }

            $attachments[] = [
                'path' => $document['path'],
                'name' => $document['attachment_name'] ?? ($document['filename'] ?? basename($document['path'])),
            ];
        }

        return $attachments;
    }

    public function mapDocumentsToAttachmentLabels(array $documents): array
    {
        $labels = [];

        foreach ($documents as $document) {
            $labels[] = [
                'name' => $document['attachment_name'] ?? ($document['filename'] ?? 'Assessment.pdf'),
            ];
        }

        return $labels;
    }

    public function listAttachmentLabels(int $recruitmentId): array
    {
        try {
            $context = $this->loadRecruitmentContext($recruitmentId);
        } catch (\RuntimeException $e) {
            return [];
        }

        $labels = [];

        foreach ($context['sessions'] as $session) {
            if (empty($session->result_json)) {
                continue;
            }

            $categoryName = trim((string) ($session->category_name ?? 'Assessment'));
            $labels[] = [
                'name' => $this->buildAttachmentDisplayName($context['candidate_name'], $categoryName),
            ];
        }

        return $labels;
    }

    public function cleanupDocuments(array $documents): void
    {
        foreach ($documents as $document) {
            $fullPath = $document['full_path'] ?? null;

            if (!$fullPath && !empty($document['path'])) {
                $fullPath = public_path($document['path']);
            }

            if ($fullPath && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private function loadRecruitmentContext(int $recruitmentId): array
    {
        $candidate = NewRecruitment::find($recruitmentId);
        if (!$candidate) {
            throw new \RuntimeException('Data kandidat tidak ditemukan.');
        }

        $attempt = DB::table('assessment_attempts')
            ->where('recruitment_id', $recruitmentId)
            ->orderByDesc('id')
            ->first();

        if (!$attempt) {
            throw new \RuntimeException('Assessment belum dimulai untuk kandidat ini.');
        }

        $sessions = DB::table('assessment_sessions')
            ->where('assessment_attempt_id', $attempt->id)
            ->where('status', 'completed')
            ->orderBy('session_order')
            ->get();

        if ($sessions->isEmpty()) {
            throw new \RuntimeException('Belum ada sesi assessment yang selesai.');
        }

        return [
            'candidate' => $candidate,
            'attempt' => $attempt,
            'sessions' => $sessions,
            'candidate_name' => trim((string) ($candidate->nama_lengkap ?? 'Kandidat')),
        ];
    }

    private function renderSessionPdf(
        $session,
        array $result,
        string $engine,
        string $candidateName,
        string $outputDir,
        bool $persist
    ): ?array {
        $categoryName = trim((string) ($session->category_name ?? 'Assessment'));
        $viewData = $this->buildViewData($session, $result, $engine, $candidateName, $categoryName);
        $viewName = $this->resolveViewName($engine);

        $attachmentName = $this->buildAttachmentDisplayName($candidateName, $categoryName);
        $filename = $persist
            ? $this->buildPdfFilename($candidateName, $categoryName)
            : uniqid('assessment_', true) . '_' . $attachmentName;
        $relativeDir = $persist ? self::OUTPUT_DIR : self::TEMP_DIR;
        $fullPath = rtrim($outputDir, '/') . '/' . $filename;

        $html = view($viewName, $viewData)->render();

        $mpdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ]);

        $chartFiles = $viewData['disc_chart_files'] ?? [];
        foreach ($chartFiles as $chartFile) {
            if (is_string($chartFile) && is_file($chartFile)) {
                $mpdf->imageVars['discProfileChart'] = file_get_contents($chartFile);
                break;
            }
        }

        try {
            $mpdf->WriteHTML($html);
            $mpdf->Output($fullPath, Destination::FILE);
        } finally {
            foreach ($chartFiles as $chartFile) {
                if (is_string($chartFile) && is_file($chartFile)) {
                    @unlink($chartFile);
                }
            }
        }

        if (!is_file($fullPath)) {
            return null;
        }

        return [
            'session_id' => (int) $session->id,
            'session_order' => (int) $session->session_order,
            'category_name' => $categoryName,
            'engine' => $engine ?: 'generic',
            'filename' => $filename,
            'attachment_name' => $attachmentName,
            'path' => $relativeDir . '/' . $filename,
            'full_path' => $fullPath,
            'url' => $persist ? $this->buildPublicUrl($filename) : null,
            'scored_at' => $result['scored_at'] ?? $session->completed_at,
        ];
    }

    private function buildViewData($session, array $result, string $engine, string $candidateName, string $categoryName): array
    {
        $base = [
            'candidate_name' => $candidateName,
            'category_name' => $categoryName,
            'session_order' => (int) $session->session_order,
            'completed_at' => $result['scored_at'] ?? $session->completed_at,
            'generated_at' => Carbon::now()->format('d/m/Y H:i'),
        ];

        if ($engine === 'disc') {
            $discDetail = $this->buildDiscDetail($result);
            $chartFile = app(DiscProfileChartRenderer::class)->renderToFile($discDetail);
            if ($chartFile) {
                $discDetail['chart_image'] = $chartFile;
            }

            return array_merge($base, [
                'disc_detail' => $discDetail,
                'disc_chart_files' => array_values(array_filter([$chartFile])),
                'summary' => $this->buildSessionResultSummary($session, $result),
            ]);
        }

        if ($engine === 'papi_kostick') {
            return array_merge($base, [
                'papi_detail' => $this->buildPapiDetail($result),
                'summary' => $this->buildSessionResultSummary($session, $result),
            ]);
        }

        return array_merge($base, [
            'generic_detail' => $this->buildGenericDetail($session, $result),
            'summary' => $this->buildSessionResultSummary($session, $result),
        ]);
    }

    private function resolveViewName(string $engine): string
    {
        if ($engine === 'disc') {
            return 'pdf.assessment.disc';
        }

        if ($engine === 'papi_kostick') {
            return 'pdf.assessment.papi';
        }

        return 'pdf.assessment.generic';
    }

    protected function buildGenericDetail($session, array $result): array
    {
        $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
        $answers = json_decode($session->answers_json ?: '{}', true) ?: [];

        usort($questions, function ($a, $b) {
            return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
        });

        $rows = [];

        foreach ($questions as $index => $question) {
            $qId = (string) ($question['id'] ?? '');
            $rawAnswer = $answers[$qId] ?? $answers[(int) $qId] ?? null;

            $selectedIds = [];
            if (is_array($rawAnswer)) {
                $selectedIds = array_map('strval', $rawAnswer);
            } elseif ($rawAnswer !== null && $rawAnswer !== '') {
                $selectedIds = [(string) $rawAnswer];
            }

            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            $selectedLabels = [];
            $isCorrect = null;

            foreach ($options as $opt) {
                $optId = (string) ($opt['id'] ?? '');
                if (!in_array($optId, $selectedIds, true)) {
                    continue;
                }

                $selectedLabels[] = trim((string) ($opt['text'] ?? $optId));
                if (array_key_exists('is_correct', $opt)) {
                    $isCorrect = (bool) $opt['is_correct'];
                }
            }

            $answerKey = array_map('strval', (array) ($question['answer_key'] ?? []));
            if ($isCorrect === null && !empty($answerKey)) {
                $normalizedSelected = $selectedIds;
                sort($normalizedSelected);
                $normalizedKey = $answerKey;
                sort($normalizedKey);
                $isCorrect = $normalizedSelected === $normalizedKey;
            }

            $correctLabels = [];
            foreach ($options as $opt) {
                if (!empty($opt['is_correct'])) {
                    $correctLabels[] = trim((string) ($opt['text'] ?? $opt['id']));
                }
            }

            if (empty($correctLabels) && !empty($answerKey)) {
                foreach ($options as $opt) {
                    if (in_array((string) ($opt['id'] ?? ''), $answerKey, true)) {
                        $correctLabels[] = trim((string) ($opt['text'] ?? $opt['id']));
                    }
                }
            }

            $rows[] = [
                'no' => $index + 1,
                'question' => trim((string) ($question['text'] ?? '-')),
                'selected' => !empty($selectedLabels) ? implode('; ', $selectedLabels) : '-',
                'is_correct' => $isCorrect,
                'correct_answer' => !empty($correctLabels) ? implode('; ', $correctLabels) : '-',
            ];
        }

        return [
            'rows' => $rows,
            'score' => $result['score'] ?? null,
            'correct_answers' => $result['correct_answers'] ?? null,
            'total_questions' => (int) ($result['total_questions'] ?? count($rows)),
            'answered' => (int) ($result['answered'] ?? count($rows)),
            'scored_at' => $result['scored_at'] ?? $session->completed_at,
        ];
    }

    private function buildPdfFilename(string $candidateName, string $categoryName): string
    {
        $microStart = str_replace('.', '', (string) microtime(true));
        $microEnd = str_replace('.', '', (string) microtime(true));
        $datetime = Carbon::now()->format('Y-m-d_H-i-s');

        return sprintf(
            '%s_%s_%s_%s_%s.pdf',
            $microStart,
            $this->sanitizeFilenamePart($candidateName),
            $this->sanitizeFilenamePart($categoryName),
            $datetime,
            $microEnd
        );
    }

    private function buildAttachmentDisplayName(string $candidateName, string $categoryName): string
    {
        return sprintf(
            '%s_%s.pdf',
            $this->sanitizeFilenamePart($categoryName),
            $this->sanitizeFilenamePart($candidateName)
        );
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?: 'unknown';
        $value = trim($value, '_');

        return $value !== '' ? substr($value, 0, 80) : 'unknown';
    }

    private function buildPublicUrl(string $filename): string
    {
        $relativePath = self::OUTPUT_DIR . '/' . rawurlencode($filename);
        $base = rtrim((string) env('APP_URL_PATH', ''), '/');

        if ($base !== '') {
            return $base . '/' . $relativePath;
        }

        $base = rtrim((string) env('APP_URL', ''), '/');
        if ($base === '') {
            return $relativePath;
        }

        if (substr($base, -7) === '/public') {
            return $base . '/' . self::OUTPUT_DIR . '/' . rawurlencode($filename);
        }

        return $base . '/public/' . self::OUTPUT_DIR . '/' . rawurlencode($filename);
    }
}
