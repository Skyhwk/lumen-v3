<?php

namespace App\Services;

use App\Models\SalaryAdjustmentRequest;
use Illuminate\Support\Facades\Log;
use Mpdf\Output\Destination;

class SalaryAdjustmentEmailAttachmentService
{
    private const TEMP_DIR = 'temp/salary-adjustment';

    /** @var GenerateAssessmentDocumentService */
    private $assessmentDocumentService;

    public function __construct(GenerateAssessmentDocumentService $assessmentDocumentService = null)
    {
        $this->assessmentDocumentService = $assessmentDocumentService ?: app(GenerateAssessmentDocumentService::class);
    }

    public function build(SalaryAdjustmentRequest $request, array $bundle): array
    {
        $employeeName = trim((string) ($bundle['request']['nama_lengkap'] ?? 'Karyawan'));
        $batchId = str_replace('.', '', uniqid('adj_mail_', true));
        $relativeDir = self::TEMP_DIR . '/' . $batchId;
        $outputDir = base_path('public/' . $relativeDir);

        if (!is_dir($outputDir) && !@mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new \RuntimeException('Gagal membuat folder lampiran email.');
        }

        $documents = [];
        $cleanupDirs = [$outputDir];

        $kpi = $this->normalizeArray($bundle['kpi'] ?? null);
        if ($this->hasKpiData($kpi)) {
            $kpiPdf = $this->renderSupportPdf(
                'pdf.salary-adjustment.kpi',
                [
                    'employee_name' => $employeeName,
                    'manager_name' => trim((string) ($bundle['request']['manager_nama'] ?? '')),
                    'kpi' => $kpi,
                ],
                'KPI_Manager',
                $employeeName,
                $outputDir,
                $relativeDir
            );
            if ($kpiPdf) {
                $documents[] = $kpiPdf;
            }
        }

        $assessmentPack = $this->buildAssessmentDocuments($request, $bundle, $employeeName);
        foreach ($assessmentPack['documents'] as $document) {
            $documents[] = $document;
        }
        if (!empty($assessmentPack['batch_dir'])) {
            $cleanupDirs[] = $assessmentPack['batch_dir'];
        }

        $attendancePdf = $this->renderSupportPdf(
            'pdf.salary-adjustment.attendance',
            ['employee_name' => $employeeName, 'attendance' => $bundle['attendance'] ?? []],
            'Absensi',
            $employeeName,
            $outputDir,
            $relativeDir
        );
        if ($attendancePdf && $this->hasAttendanceData($bundle['attendance'] ?? null)) {
            $documents[] = $attendancePdf;
        }

        $counseling = $this->normalizeArray($bundle['counseling'] ?? null);
        if ($this->hasCounselingData($counseling)) {
            $counselingPdf = $this->renderSupportPdf(
                'pdf.salary-adjustment.counseling',
                ['employee_name' => $employeeName, 'counseling' => $counseling],
                'Hasil_Konseling',
                $employeeName,
                $outputDir,
                $relativeDir
            );
            if ($counselingPdf) {
                $documents[] = $counselingPdf;
            }
        }

        return [
            'attachments' => $this->assessmentDocumentService->buildSendEmailAttachments($documents),
            'documents' => $documents,
            'cleanup_dirs' => array_values(array_unique(array_filter($cleanupDirs))),
            'failed_sessions' => $assessmentPack['failed_sessions'] ?? [],
        ];
    }

    public function cleanup(array $pack): void
    {
        $this->assessmentDocumentService->cleanupDocuments($pack['documents'] ?? []);

        foreach ($pack['cleanup_dirs'] ?? [] as $directory) {
            if (!$directory || !is_dir($directory)) {
                continue;
            }
            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($directory);
        }
    }

    private function buildAssessmentDocuments(SalaryAdjustmentRequest $request, array $bundle, string $employeeName): array
    {
        $request->loadMissing('assessment.sessions');
        $reportById = [];
        foreach (($bundle['assessment_report']['sessions'] ?? []) as $session) {
            if (!empty($session['id'])) {
                $reportById[(int) $session['id']] = $session;
            }
        }

        $prepared = [];
        $sessions = $request->assessment ? $request->assessment->sessions : collect();
        foreach ($sessions as $session) {
            $report = $reportById[(int) $session->id] ?? null;
            $engine = strtolower((string) ($report['engine'] ?? ''));
            $categoryName = strtoupper(trim((string) $session->category_name));

            if ($engine === '16pf' || $categoryName === '16PF') {
                continue;
            }

            $kind = $this->resolveAssessmentKind($engine, $session->category_name);
            if ($kind === null) {
                continue;
            }

            if (!$this->sessionHasResult($session, $report)) {
                continue;
            }

            $displayName = $kind === 'ketelitian' ? 'Ketelitian' : $session->category_name;
            $result = $this->scoreSessionResult($session, $report);

            $prepared[] = (object) [
                'id' => $session->id,
                'session_order' => $session->session_order,
                'category_name' => $displayName,
                'questions_json' => is_string($session->questions_json)
                    ? $session->questions_json
                    : json_encode($session->questions_json ?: []),
                'answers_json' => is_string($session->answers_json)
                    ? $session->answers_json
                    : json_encode($session->answers_json ?: []),
                'result_json' => json_encode($result),
                'completed_at' => $session->completed_at,
                'status' => $session->status,
            ];
        }

        if (empty($prepared)) {
            return ['documents' => [], 'batch_dir' => null, 'failed_sessions' => []];
        }

        return $this->assessmentDocumentService->generateFromSessions($employeeName, $prepared, false);
    }

    private function resolveAssessmentKind(string $engine, ?string $categoryName): ?string
    {
        if ($engine === 'disc') {
            return 'disc';
        }
        if ($engine === 'papi_kostick') {
            return 'papi';
        }

        $normalized = strtoupper(trim((string) $categoryName));
        if ($normalized === 'DISC') {
            return 'disc';
        }
        if (in_array($normalized, ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return 'papi';
        }
        if ($this->isKetelitianCategory($normalized)) {
            return 'ketelitian';
        }

        return null;
    }

    private function isKetelitianCategory(string $normalized): bool
    {
        return strpos($normalized, 'NALAR') !== false
            || strpos($normalized, 'KETELITIAN') !== false
            || strpos($normalized, 'ACCURACY') !== false
            || $normalized === 'TKB';
    }

    private function sessionHasResult($session, ?array $report): bool
    {
        if (!empty($report['has_result']) || !empty($report['disc_detail']) || !empty($report['papi_detail'])) {
            return true;
        }

        $result = is_array($session->result_json) ? $session->result_json : [];
        if (!empty($result)) {
            return true;
        }

        $status = strtolower((string) ($session->status ?? ''));
        return in_array($status, ['completed', 'selesai'], true) && !empty($session->answers_json);
    }

    private function scoreSessionResult($session, ?array $report): array
    {
        $questions = is_array($session->questions_json) ? $session->questions_json : [];
        $answers = is_array($session->answers_json) ? $session->answers_json : [];
        $stored = is_array($session->result_json) ? $session->result_json : [];

        if (!empty($questions) && !empty($answers)) {
            try {
                $scored = (new PsychometricScoringService())->scoreSession(
                    (string) $session->category_name,
                    $questions,
                    $answers
                );
                if (!empty($scored)) {
                    $stored = $scored;
                }
            } catch (\Throwable $e) {
                Log::warning('Salary adjustment assessment rescore failed', [
                    'session_id' => $session->id ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($report['engine'])) {
            $stored['engine'] = $report['engine'];
        }
        if (!empty($report['scored_at'])) {
            $stored['scored_at'] = $report['scored_at'];
        } elseif ($session->completed_at && empty($stored['scored_at'])) {
            $stored['scored_at'] = $session->completed_at;
        }

        return $stored;
    }

    private function hasKpiData(?array $kpi): bool
    {
        if (!$kpi) {
            return false;
        }

        return !empty($kpi['items'])
            || trim((string) ($kpi['summary'] ?? '')) !== ''
            || trim((string) ($kpi['strengths'] ?? '')) !== ''
            || trim((string) ($kpi['improvements'] ?? '')) !== ''
            || ($kpi['total_score_avg'] ?? '') !== ''
            || ($kpi['total_final_score'] ?? '') !== '';
    }

    private function hasAttendanceData($attendance): bool
    {
        if (!is_array($attendance)) {
            return false;
        }

        return !empty($attendance['monthly']) || !empty($attendance['daily']) || !empty($attendance['summary']);
    }

    private function hasCounselingData(?array $counseling): bool
    {
        if (!$counseling) {
            return false;
        }

        return trim((string) ($counseling['result_notes'] ?? '')) !== ''
            || !empty($counseling['scheduled_date'])
            || !empty($counseling['counselor_name']);
    }

    private function renderSupportPdf(
        string $view,
        array $data,
        string $label,
        string $employeeName,
        string $outputDir,
        string $relativeDir
    ): ?array {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $employeeName) ?: 'Karyawan';
        $filename = $label . '_' . $safeName . '.pdf';
        $fullPath = rtrim($outputDir, '/') . '/' . $filename;
        $mpdfTemp = rtrim($outputDir, '/') . '/mpdf_' . str_replace('.', '', uniqid($label . '_', true));

        if (!is_dir($mpdfTemp) && !@mkdir($mpdfTemp, 0775, true) && !is_dir($mpdfTemp)) {
            throw new \RuntimeException('Gagal membuat folder sementara mPDF.');
        }

        try {
            $html = view($view, $data)->render();
            $mpdf = new MpdfService([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'tempDir' => $mpdfTemp,
            ]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($fullPath, Destination::FILE);
        } catch (\Throwable $e) {
            Log::warning('Salary adjustment support PDF failed', [
                'label' => $label,
                'message' => $e->getMessage(),
            ]);
            return null;
        } finally {
            unset($mpdf);
            $this->removeDirectory($mpdfTemp);
        }

        clearstatcache(true, $fullPath);
        if (!is_file($fullPath) || filesize($fullPath) < 1) {
            return null;
        }

        return [
            'filename' => $filename,
            'attachment_name' => $filename,
            'path' => $relativeDir . '/' . $filename,
            'full_path' => $fullPath,
            'batch_dir' => $outputDir,
        ];
    }

    private function normalizeArray($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            $encoded = json_decode(json_encode($value), true);
            return is_array($encoded) ? $encoded : null;
        }

        return null;
    }

    private function removeDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }
            @unlink($path);
        }
        @rmdir($directory);
    }
}
