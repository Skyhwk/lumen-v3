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

    public function generateForRecruitment(int $recruitmentId): array
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

        $outputDir = base_path('public/' . self::OUTPUT_DIR);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $documents = [];
        $candidateName = trim((string) ($candidate->nama_lengkap ?? 'Kandidat'));

        foreach ($sessions as $session) {
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
                    $candidateName,
                    $outputDir
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
            }
        }

        if (empty($documents)) {
            throw new \RuntimeException('Tidak ada dokumen PDF yang berhasil dibuat.');
        }

        return [
            'recruitment_id' => $recruitmentId,
            'attempt_id' => (int) $attempt->id,
            'candidate_name' => $candidateName,
            'documents' => $documents,
        ];
    }

    private function renderSessionPdf($session, array $result, string $engine, string $candidateName, string $outputDir): ?array
    {
        $categoryName = trim((string) ($session->category_name ?? 'Assessment'));
        $viewData = $this->buildViewData($session, $result, $engine, $candidateName, $categoryName);
        $viewName = $this->resolveViewName($engine);

        $html = view($viewName, $viewData)->render();

        $mpdf = new MpdfService([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
        ]);

        
        $mpdf->WriteHTML($html);

        $filename = $this->buildPdfFilename($candidateName, $categoryName);
        $fullPath = rtrim($outputDir, '/') . '/' . $filename;
        $mpdf->Output($fullPath, Destination::FILE);

        if (!is_file($fullPath)) {
            return null;
        }

        return [
            'session_id' => (int) $session->id,
            'session_order' => (int) $session->session_order,
            'category_name' => $categoryName,
            'engine' => $engine ?: 'generic',
            'filename' => $filename,
            'path' => self::OUTPUT_DIR . '/' . $filename,
            'url' => $this->buildPublicUrl($filename),
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
            return array_merge($base, [
                'disc_detail' => $this->buildDiscDetail($result),
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

    private function sanitizeFilenamePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?: 'unknown';
        $value = trim($value, '_');

        return $value !== '' ? substr($value, 0, 80) : 'unknown';
    }

    private function buildFormalHeaderHtml(): string
    {
        return '
            <div style="border-bottom: 1px solid #000; padding-bottom: 5px; font-family: Times New Roman, Times, serif;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size: 12pt; font-weight: bold; color: #000;">INTILAB</td>
                        <td style="text-align: right; font-size: 9pt; color: #333;">Dokumen Assessment</td>
                    </tr>
                </table>
            </div>
        ';
    }

    private function buildFormalFooterHtml(): string
    {
        return '
            <div style="border-top: 1px solid #666; padding-top: 4px; font-family: Times New Roman, Times, serif; font-size: 8pt; color: #333;">
                <table width="100%">
                    <tr>
                        <td>Dokumen ini dihasilkan secara otomatis oleh sistem INTILAB ATS</td>
                        <td style="text-align: right;">Halaman {PAGENO} / {nbpg}</td>
                    </tr>
                </table>
            </div>
        ';
    }

    private function buildHeaderHtml(string $candidateName, string $categoryName): string
    {
        $candidate = htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8');
        $category = htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8');

        return '
            <div style="border-bottom: 2px solid #0f3460; padding-bottom: 6px; font-family: Helvetica, Arial, sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size: 18px; font-weight: bold; color: #0f3460;">INTILAB</td>
                        <td style="text-align: right; font-size: 10px; color: #64748b;">
                            Hasil Assessment<br/>
                            <span style="font-weight: bold; color: #0f3460;">' . $category . '</span>
                        </td>
                    </tr>
                </table>
                <div style="font-size: 10px; color: #475569; margin-top: 4px;">Kandidat: ' . $candidate . '</div>
            </div>
        ';
    }

    private function buildFooterHtml(): string
    {
        $year = date('Y');

        return '
            <div style="border-top: 1px solid #cbd5e1; padding-top: 5px; font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #94a3b8;">
                <table width="100%">
                    <tr>
                        <td>Dokumen dihasilkan otomatis oleh sistem INTILAB ATS</td>
                        <td style="text-align: right;">Halaman {PAGENO} / {nbpg}</td>
                    </tr>
                </table>
                <div style="text-align: center; margin-top: 2px;">&copy; ' . $year . ' INTILAB</div>
            </div>
        ';
    }

    private function buildPublicUrl(string $filename): string
    {
        $baseUrl = rtrim((string) env('APP_URL', ''), '/');

        if ($baseUrl === '') {
            return self::OUTPUT_DIR . '/' . $filename;
        }

        return $baseUrl . '/public/' . self::OUTPUT_DIR . '/' . rawurlencode($filename);
    }
}
