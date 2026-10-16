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

    public const FAILURE_LOG_SUBDIR = 'internal-assessment-pdf/failures';

    public const STAGE_GENERATE_PDF = 'generate_pdf';

    public const STAGE_SEND_EMAIL = 'send_email';

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
     * @return array{generated: array, failed: array, failed_email: array, emailed: array, skipped_email: array, failure_log_path: string|null}
     */
    public function processAssessment(
        int $assessmentId,
        ?string $emailFilter = null,
        bool $sendEmail = false,
        bool $dryRun = false,
        ?int $attemptIdFilter = null,
        bool $retryFailedOnly = false,
        string $source = 'cli'
    ): array {
        $assessment = DB::table('assessment_internal')->where('id', $assessmentId)->first();
        if (!$assessment) {
            throw new \RuntimeException('Assessment tidak ditemukan.');
        }

        $retryAttemptIds = $retryFailedOnly
            ? $this->getUnresolvedAttemptIds($assessmentId)
            : null;

        if ($retryFailedOnly && $retryAttemptIds === []) {
            throw new \RuntimeException('Tidak ada kegagalan terbuka di log storage untuk assessment ini.');
        }

        $attempts = $this->resolveAttempts($assessmentId, $emailFilter, $attemptIdFilter, $retryAttemptIds);
        if ($attempts->isEmpty()) {
            throw new \RuntimeException('Tidak ada peserta yang cocok dengan filter.');
        }

        $generated = [];
        $failed = [];
        $failedEmail = [];
        $emailed = [];
        $skippedEmail = [];
        $failureLogPath = $this->failureLogPath($assessmentId);

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
                $this->logFailureResolved($assessmentId, $attemptId, self::STAGE_GENERATE_PDF, $source);

                $row = [
                    'attempt_id' => $attemptId,
                    'participant' => $label,
                    'links' => $payload['links'],
                    'link' => $payload['link'],
                ];
                $generated[] = $row;

                if ($sendEmail) {
                    if (empty($attempt->email)) {
                        $reason = 'Email peserta kosong';
                        $skippedEmail[] = array_merge($row, ['reason' => $reason]);
                        $this->logFailure(
                            $assessmentId,
                            $attemptId,
                            self::STAGE_SEND_EMAIL,
                            $reason,
                            [
                                'participant' => $label,
                                'email' => null,
                            ],
                            $source
                        );
                        $failedEmail[] = array_merge($row, ['message' => $reason]);
                    } else {
                        try {
                            $attachmentPaths = array_column($payload['links'], 'link');
                            $this->sendParticipantPdfEmail($attempt, $assessment, $attachmentPaths);
                            $this->logFailureResolved($assessmentId, $attemptId, self::STAGE_SEND_EMAIL, $source);
                            $emailed[] = $row;
                            sleep(self::EMAIL_SEND_DELAY_SECONDS);
                        } catch (\Throwable $emailError) {
                            $message = $emailError->getMessage();
                            $this->logFailure(
                                $assessmentId,
                                $attemptId,
                                self::STAGE_SEND_EMAIL,
                                $message,
                                [
                                    'participant' => $label,
                                    'email' => $attempt->email,
                                    'pdf_paths' => $attachmentPaths ?? [],
                                ],
                                $source
                            );
                            Log::error('Internal assessment PDF email failed', [
                                'assessment_id' => $assessmentId,
                                'attempt_id' => $attemptId,
                                'message' => $message,
                            ]);
                            $failedEmail[] = array_merge($row, ['message' => $message]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->logFailure(
                    $assessmentId,
                    $attemptId,
                    self::STAGE_GENERATE_PDF,
                    $message,
                    ['participant' => $label, 'email' => $attempt->email ?? null],
                    $source
                );
                Log::error('Internal assessment PDF generate failed', [
                    'assessment_id' => $assessmentId,
                    'attempt_id' => $attemptId,
                    'message' => $message,
                ]);
                $failed[] = [
                    'attempt_id' => $attemptId,
                    'participant' => $label,
                    'stage' => self::STAGE_GENERATE_PDF,
                    'message' => $message,
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
            'failed_email' => $failedEmail,
            'emailed' => $emailed,
            'skipped_email' => $skippedEmail,
            'failure_log_path' => $failureLogPath,
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

    public function failureLogPath(int $assessmentId): string
    {
        $dir = storage_path('app/' . self::FAILURE_LOG_SUBDIR);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'assessment_' . $assessmentId . '.jsonl';
    }

    /**
     * @return array<int, array{attempt_id: int, stage: string, message: string, at: string, email: string|null}>
     */
    public function getUnresolvedFailures(int $assessmentId): array
    {
        $path = $this->failureLogPath($assessmentId);
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $state */
        $state = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded) || empty($decoded['attempt_id']) || empty($decoded['stage'])) {
                continue;
            }
            $key = (int) $decoded['attempt_id'] . '|' . (string) $decoded['stage'];
            $event = (string) ($decoded['event'] ?? 'failed');
            if ($event === 'resolved') {
                unset($state[$key]);
                continue;
            }
            $state[$key] = $decoded;
        }

        $out = [];
        foreach ($state as $row) {
            $out[] = [
                'attempt_id' => (int) $row['attempt_id'],
                'stage' => (string) $row['stage'],
                'message' => (string) ($row['message'] ?? ''),
                'at' => (string) ($row['at'] ?? ''),
                'email' => isset($row['context']['email']) ? (string) $row['context']['email'] : null,
            ];
        }

        usort($out, fn ($a, $b) => $a['attempt_id'] <=> $b['attempt_id']);

        return $out;
    }

    /**
     * @return int[]
     */
    public function getUnresolvedAttemptIds(int $assessmentId): array
    {
        $ids = [];
        foreach ($this->getUnresolvedFailures($assessmentId) as $row) {
            $ids[$row['attempt_id']] = $row['attempt_id'];
        }

        return array_values($ids);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logFailure(
        int $assessmentId,
        int $attemptId,
        string $stage,
        string $message,
        array $context = [],
        string $source = 'cli'
    ): void {
        $this->appendFailureLogLine($assessmentId, [
            'event' => 'failed',
            'at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'assessment_id' => $assessmentId,
            'attempt_id' => $attemptId,
            'stage' => $stage,
            'source' => $source,
            'message' => $message,
            'context' => $context,
        ]);
    }

    public function logFailureResolved(
        int $assessmentId,
        int $attemptId,
        string $stage,
        string $source = 'cli'
    ): void {
        $this->appendFailureLogLine($assessmentId, [
            'event' => 'resolved',
            'at' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'assessment_id' => $assessmentId,
            'attempt_id' => $attemptId,
            'stage' => $stage,
            'source' => $source,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendFailureLogLine(int $assessmentId, array $payload): void
    {
        $path = $this->failureLogPath($assessmentId);
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            Log::warning('Internal assessment PDF failure log encode failed', [
                'assessment_id' => $assessmentId,
            ]);

            return;
        }

        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function resolveAttempts(
        int $assessmentId,
        ?string $emailFilter,
        ?int $attemptIdFilter = null,
        ?array $attemptIdsOnly = null
    ) {
        $query = DB::table('assessment_internal_attempts')
            ->where('assessment_internal_id', $assessmentId)
            ->orderBy('participant_name');

        if ($emailFilter !== null && trim($emailFilter) !== '') {
            $query->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($emailFilter))]);
        }

        if ($attemptIdFilter !== null && $attemptIdFilter > 0) {
            $query->where('id', $attemptIdFilter);
        }

        if ($attemptIdsOnly !== null && $attemptIdsOnly !== []) {
            $query->whereIn('id', $attemptIdsOnly);
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
