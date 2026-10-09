<?php

namespace App\Services;

use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Output\Destination;
use App\Services\SendEmail;

class InternalAssessmentParticipantPdfService
{
    use BuildsCandidateAssessmentPreview;

    public const OUTPUT_DIR = 'Export_Assessment_Internal_Pdf';

    private const PDF_CATEGORY_ORDER = ['LOGIKA', 'NALAR'];

    private const EMAIL_SEND_DELAY_SECONDS = 3;

    public function generateForAttempt(int $attemptId): array
    {
        $context = $this->loadAttemptContext($attemptId);
        $sections = $this->buildPdfSections($context['sessions'], $context['attempt']);

        if (empty($sections)) {
            throw new \RuntimeException('Belum ada data sesi Logika atau Nalar yang bisa diekspor untuk peserta ini.');
        }

        $participantName = trim((string) ($context['attempt']->participant_name ?? 'Peserta'));
        $links = [];
        foreach ($sections as $section) {
            $links[] = [
                'category' => $section['category_name'],
                'link' => $this->renderSessionPdf($context, $section, $participantName),
            ];
        }

        $this->removeLegacyCombinedPdf($context, $participantName);

        return [
            'message' => count($links) . ' PDF laporan sesi berhasil dibuat',
            'links' => $links,
            'link' => $links[0]['link'] ?? null,
            'attempt_id' => $attemptId,
        ];
    }

    /**
     * Generate (dan opsional kirim email) untuk semua atau satu peserta di assessment.
     *
     * @return array{generated: array, failed: array, emailed: array, skipped_email: array}
     */
    public function processAssessment(
        int $assessmentId,
        ?string $emailFilter = null,
        bool $sendEmail = false,
        bool $dryRun = false
    ): array {
        $assessment = DB::table('assessment_internal')->where('id', $assessmentId)->first();
        if (!$assessment) {
            throw new \RuntimeException('Assessment tidak ditemukan.');
        }

        $attempts = $this->resolveAttempts($assessmentId, $emailFilter);
        if ($attempts->isEmpty()) {
            throw new \RuntimeException('Tidak ada peserta yang cocok dengan filter.');
        }

        $generated = [];
        $failed = [];
        $emailed = [];
        $skippedEmail = [];

        foreach ($attempts as $attempt) {
            $attemptId = (int) $attempt->id;
            $label = trim((string) ($attempt->participant_name ?? '')) . ' <' . ($attempt->email ?? '-') . '>';

            if ($dryRun) {
                $generated[] = [
                    'attempt_id' => $attemptId,
                    'participant' => $label,
                    'dry_run' => true,
                ];
                continue;
            }

            try {
                $payload = $this->generateForAttempt($attemptId);
                $row = [
                    'attempt_id' => $attemptId,
                    'participant' => $label,
                    'links' => $payload['links'],
                    'link' => $payload['link'],
                ];

                if ($sendEmail) {
                    if (empty($attempt->email)) {
                        $skippedEmail[] = array_merge($row, ['reason' => 'Email peserta kosong']);
                    } else {
                        $attachmentPaths = array_column($payload['links'], 'link');
                        $this->sendParticipantPdfEmail($attempt, $assessment, $attachmentPaths);
                        $emailed[] = $row;
                        sleep(self::EMAIL_SEND_DELAY_SECONDS);
                    }
                }

                $generated[] = $row;
            } catch (\Throwable $e) {
                Log::warning('Internal assessment PDF skipped for attempt', [
                    'attempt_id' => $attemptId,
                    'message' => $e->getMessage(),
                ]);
                $failed[] = [
                    'attempt_id' => $attemptId,
                    'participant' => $label,
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (!$dryRun && empty($generated)) {
            throw new \RuntimeException('Tidak ada PDF yang berhasil dibuat.');
        }

        return [
            'assessment_id' => $assessmentId,
            'generated' => $generated,
            'failed' => $failed,
            'emailed' => $emailed,
            'skipped_email' => $skippedEmail,
        ];
    }

    /** @deprecated Gunakan processAssessment(); tetap dipakai worker batch. */
    public function generateForAssessment(int $assessmentId): array
    {
        $result = $this->processAssessment($assessmentId, null, false, false);

        return [
            'message' => count($result['generated']) . ' PDF peserta berhasil dibuat',
            'generated' => $result['generated'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * @param string[] $relativePaths
     */
    public function sendParticipantPdfEmail($attempt, $assessment, array $relativePaths): void
    {
        $email = trim((string) ($attempt->email ?? ''));
        if ($email === '') {
            throw new \RuntimeException('Email peserta kosong.');
        }

        if ($relativePaths === []) {
            throw new \RuntimeException('Tidak ada lampiran PDF untuk dikirim.');
        }

        $attachments = [];
        foreach ($relativePaths as $relativePath) {
            $publicRelative = ltrim(str_replace('\\', '/', $relativePath), '/');
            $fullPath = public_path($publicRelative);
            if (!is_file($fullPath) || filesize($fullPath) < 1) {
                throw new \RuntimeException('File PDF tidak ditemukan, email tidak dikirim: ' . $publicRelative);
            }
            $attachments[] = [
                'path' => $publicRelative,
                'name' => basename($publicRelative),
            ];
        }

        $body = view('Email.internal-assessment-nalar-logika-pdf', [
            'name' => $attempt->participant_name ?: 'Peserta',
            'assessmentName' => $assessment->nama_assesment ?? 'Assessment Internal',
        ])->render();

        SendEmail::where('to', $email)
            ->where('subject', 'Laporan Jawaban Assessment Internal - PT Inti Surya Laboratorium')
            ->where('body', $body)
            ->where('attachment', $attachments)
            ->where('cc', [])
            ->where('bcc', [])
            ->where('karyawan', 'Internal Assessment System')
            ->noReply('PT Inti Surya Laboratorium')
            ->replyToAtsHrd()
            ->send();
    }

    private function resolveAttempts(int $assessmentId, ?string $emailFilter)
    {
        $query = DB::table('assessment_internal_attempts')
            ->where('assessment_internal_id', $assessmentId)
            ->orderBy('participant_name');

        if ($emailFilter !== null && trim($emailFilter) !== '') {
            $query->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($emailFilter))]);
        }

        return $query->get();
    }

    private function loadAttemptContext(int $attemptId): array
    {
        $attempt = DB::table('assessment_internal_attempts')->where('id', $attemptId)->first();
        if (!$attempt) {
            throw new \RuntimeException('Peserta assessment tidak ditemukan.');
        }

        $assessment = DB::table('assessment_internal')
            ->where('id', $attempt->assessment_internal_id)
            ->first();

        $sessions = DB::table('assessment_internal_sessions')
            ->where('assessment_internal_attempt_id', $attemptId)
            ->orderBy('session_order')
            ->get();

        return [
            'attempt' => $attempt,
            'assessment' => $assessment,
            'sessions' => $sessions,
        ];
    }

    private function buildPdfSections($sessions, $attempt): array
    {
        $byCategory = [];
        foreach ($sessions as $session) {
            $key = strtoupper(trim((string) ($session->category_name ?? '')));
            if (!in_array($key, self::PDF_CATEGORY_ORDER, true)) {
                continue;
            }
            if (empty($session->questions_json)) {
                continue;
            }
            $byCategory[$key] = $session;
        }

        $sections = [];
        foreach (self::PDF_CATEGORY_ORDER as $category) {
            if (!isset($byCategory[$category])) {
                continue;
            }
            $session = $byCategory[$category];
            $questions = $this->buildQuestionBlocks($session);
            if (empty($questions)) {
                continue;
            }

            $result = json_decode($session->result_json ?: '{}', true) ?: [];
            $sections[] = [
                'category_name' => $category,
                'session_title' => $this->sessionDisplayTitle($category),
                'score' => isset($result['score']) ? round((float) $result['score'], 2) : null,
                'completed_at' => $this->formatDateTime($session->completed_at ?? ($result['scored_at'] ?? null)),
                'assessment_time' => $this->formatSessionAssessmentTime($session, $attempt),
                'session' => $session,
                'questions' => $questions,
            ];
        }

        return $sections;
    }

    private function buildQuestionBlocks($session): array
    {
        $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
        $answers = json_decode($session->answers_json ?: '{}', true) ?: [];

        usort($questions, function ($a, $b) {
            return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
        });

        $blocks = [];
        foreach ($questions as $index => $question) {
            $type = strtolower(trim((string) ($question['type'] ?? 'choice')));
            if ($type === 'scale' || ($question['scoring_type'] ?? '') === 'scale_average') {
                continue;
            }

            $qId = (string) ($question['id'] ?? '');
            $rawAnswer = $answers[$qId] ?? $answers[(int) $qId] ?? null;

            $selectedIds = [];
            if (is_array($rawAnswer)) {
                $selectedIds = array_map('strval', $rawAnswer);
            } elseif ($rawAnswer !== null && $rawAnswer !== '') {
                $selectedIds = [(string) $rawAnswer];
            }

            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            $answerKey = array_map('strval', (array) ($question['answer_key'] ?? []));

            $optionRows = [];
            foreach ($options as $optIndex => $opt) {
                $optId = (string) ($opt['id'] ?? '');
                $isSelected = in_array($optId, $selectedIds, true);
                $isCorrect = !empty($opt['is_correct']);
                if (!$isCorrect && $answerKey !== []) {
                    $isCorrect = in_array($optId, $answerKey, true);
                }

                $optionRows[] = [
                    'letter' => $this->optionLetter($optIndex),
                    'label' => trim((string) ($opt['text'] ?? $opt['label'] ?? $optId)),
                    'is_selected' => $isSelected,
                    'is_correct' => $isCorrect,
                    'is_wrong' => $isSelected && !$isCorrect,
                ];
            }

            if ($optionRows === []) {
                $givenText = is_array($rawAnswer) ? implode('; ', $rawAnswer) : trim((string) ($rawAnswer ?? ''));
                $keyText = implode('; ', $answerKey);
                $normalizedGiven = mb_strtolower(trim($givenText));
                $normalizedKey = mb_strtolower(trim($keyText));
                $isCorrect = $normalizedGiven !== '' && $normalizedGiven === $normalizedKey;

                $optionRows[] = [
                    'letter' => 'J',
                    'label' => $givenText !== '' ? $givenText : '-',
                    'is_selected' => true,
                    'is_correct' => $isCorrect,
                    'is_wrong' => !$isCorrect && $givenText !== '',
                ];
            }

            $blocks[] = [
                'no' => $index + 1,
                'question' => trim((string) ($question['text'] ?? '-')),
                'options' => $optionRows,
                'answered_correctly' => $this->isQuestionAnsweredCorrectly($optionRows),
            ];
        }

        return $blocks;
    }

    private function isQuestionAnsweredCorrectly(array $optionRows): bool
    {
        if ($optionRows === []) {
            return false;
        }

        $anySelected = false;
        foreach ($optionRows as $row) {
            if (!empty($row['is_selected'])) {
                $anySelected = true;
                if (empty($row['is_correct'])) {
                    return false;
                }
            }
        }

        if (!$anySelected) {
            return false;
        }

        foreach ($optionRows as $row) {
            if (!empty($row['is_correct']) && empty($row['is_selected'])) {
                return false;
            }
        }

        return true;
    }

    private function renderSessionPdf(array $context, array $section, string $participantName): string
    {
        $attempt = $context['attempt'];
        $assessment = $context['assessment'];

        $relativeDir = $this->buildAssessmentOutputRelativeDir($assessment);
        $dir = base_path('public/' . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Gagal membuat folder PDF.');
        }

        $fileName = $this->buildFileName(
            (int) $attempt->id,
            $participantName,
            (string) $section['category_name']
        );
        $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        $tempDir = $dir . DIRECTORY_SEPARATOR . 'mpdf_' . (int) $attempt->id . '_' . strtolower($section['category_name']) . '_' . str_replace('.', '', uniqid('', true));
        if (!is_dir($tempDir) && !@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            throw new \RuntimeException('Gagal membuat folder sementara mPDF.');
        }

        $html = view('pdf.assessment.internal_nalar_logika', [
            'participant_name' => $participantName,
            'participant_email' => $attempt->email ?? '-',
            'assessment_name' => $assessment->nama_assesment ?? '-',
            'assessment_time' => $section['assessment_time'],
            'session_title' => $section['session_title'],
            'score' => $section['score'],
            'session_completed_at' => $section['completed_at'],
            'questions' => $section['questions'],
        ])->render();

        try {
            $mpdf = new MpdfService([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 12,
                'tempDir' => $tempDir,
            ]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($fullPath, Destination::FILE);
        } finally {
            $this->removeDirectory($tempDir);
        }

        clearstatcache(true, $fullPath);
        if (!is_file($fullPath) || filesize($fullPath) < 1) {
            throw new \RuntimeException('Gagal menulis file PDF.');
        }

        return $relativeDir . '/' . $fileName;
    }

    private function removeLegacyCombinedPdf(array $context, string $participantName): void
    {
        $assessment = $context['assessment'];
        $attempt = $context['attempt'];
        $relativeDir = $this->buildAssessmentOutputRelativeDir($assessment);
        $legacyName = 'Jawaban_Nalar_Logika_' . (int) $attempt->id . '_' . preg_replace('/[^\w\-]/', '_', $participantName) . '.pdf';
        $legacyPath = base_path('public/' . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir . '/' . $legacyName));
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }
    }

    private function buildAssessmentOutputRelativeDir($assessment): string
    {
        $batch = trim((string) ($assessment->batch ?? 'batch'));
        $nama = trim((string) ($assessment->nama_assesment ?? 'assessment'));
        $folderName = $this->sanitizePathSegment($batch) . '-' . $this->sanitizePathSegment($nama);

        return self::OUTPUT_DIR . '/' . $folderName;
    }

    /** Nama file stabil per sesi & attempt — render ulang menimpa PDF yang sama. */
    private function buildFileName(int $attemptId, $participantName, string $category): string
    {
        $safeName = preg_replace('/[^\w\-]/', '_', (string) $participantName);
        if ($safeName === '') {
            $safeName = 'Peserta';
        }
        $safeCategory = preg_replace('/[^\w\-]/', '_', strtoupper(trim($category)));

        return 'Jawaban_' . $safeCategory . '_' . $attemptId . '_' . $safeName . '.pdf';
    }

    private function sessionDisplayTitle(string $category): string
    {
        $map = [
            'LOGIKA' => 'Logika',
            'NALAR' => 'Nalar',
            'INTEGRITAS' => 'Integritas',
        ];

        return $map[strtoupper($category)] ?? ucfirst(strtolower($category));
    }

    private function formatSessionAssessmentTime($session, $attempt): string
    {
        $start = $session->started_at ?? $attempt->started_at ?? null;
        $end = $session->completed_at ?? null;

        if ($start && $end) {
            return $this->formatDateTime($start) . ' – ' . $this->formatDateTime($end);
        }
        if ($end) {
            return $this->formatDateTime($end);
        }
        if ($start) {
            return $this->formatDateTime($start);
        }

        return '-';
    }

    private function sanitizePathSegment(string $value): string
    {
        $value = preg_replace('/[\\\\\\/:*?"<>|]/u', '_', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);

        return $value !== '' ? $value : 'unknown';
    }

    private function formatDateTime($value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function optionLetter(int $index): string
    {
        if ($index >= 0 && $index < 26) {
            return chr(65 + $index);
        }

        return (string) ($index + 1);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
