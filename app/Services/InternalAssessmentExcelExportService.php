<?php

namespace App\Services;

use App\Helpers\ShioElemenHelper;
use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InternalAssessmentExcelExportService
{
    use BuildsCandidateAssessmentPreview;

    private const OUTPUT_DIR = 'Export_Assessment_Internal';

    public function exportForAttempt(int $attemptId): array
    {
        $context = $this->loadAttemptContext($attemptId);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Peserta');

        $row = 1;
        $row = $this->writeParticipantHeader($sheet, $row, $context);
        $row = $this->writeSessionSummarySection($sheet, $row, $context['sessions']);
        $row = $this->writeCognitiveAggregateSection($sheet, $row, $context['sessions']);
        $row = $this->writeEvaluationAggregateSections($sheet, $row, $context['sessions']);
        foreach ($context['sessions'] as $index => $session) {
            $sessionSheet = $spreadsheet->createSheet();
            // Prefix with the sequence so repeated modules/targets remain distinct.
            $title = preg_replace('/[\\\\\/\?\*\[\]:\x00-\x1F]/u', ' ', $this->internalSessionDisplayName($session));
            $sessionSheet->setTitle(mb_substr(($index + 1) . '. ' . trim($title), 0, 31));
            $sessionRow = $this->writeParticipantHeader($sessionSheet, 1, $context);
            $sessionRow = $this->writeSessionSummarySection($sessionSheet, $sessionRow, collect([$session]));
            if (empty($session->result_json)) {
                $sessionSheet->setCellValue('A' . $sessionRow, 'Hasil sesi belum tersedia.');
            } else {
                $this->writeDetailedSessionSections($sessionSheet, $sessionRow, [$session]);
            }
        }

        foreach ($spreadsheet->getAllSheets() as $reportSheet) {
            foreach (range('A', 'O') as $columnId) {
                $reportSheet->getColumnDimension($columnId)->setAutoSize(true);
            }
            $reportSheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
            $reportSheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $link = $this->saveSpreadsheet(
            $spreadsheet,
            $this->buildFileName($context['assessment']->batch ?? 'batch', $context['attempt']->participant_name ?? 'peserta')
        );

        return [
            'message' => 'Export Excel berhasil',
            'link' => $link,
        ];
    }

    public function exportAssessmentSummary(int $assessmentId): array
    {
        $assessment = DB::table('assessment_internal')->where('id', $assessmentId)->first();
        if (!$assessment) {
            throw new \RuntimeException('Assessment tidak ditemukan.');
        }

        $attempts = DB::table('assessment_internal_attempts')
            ->where('assessment_internal_id', $assessmentId)
            ->orderBy('participant_name')
            ->get();

        if ($attempts->isEmpty()) {
            throw new \RuntimeException('Belum ada peserta untuk di-export.');
        }

        $moduleColumns = [];
        $rows = [];

        foreach ($attempts as $attempt) {
            $sessions = DB::table('assessment_internal_sessions')
                ->where('assessment_internal_attempt_id', $attempt->id)
                ->orderBy('session_order')
                ->get();

            $sessionScores = [];
            foreach ($sessions as $session) {
                $label = $this->internalSessionDisplayName($session);
                $moduleColumns[$label] = true;
                $preview = $this->extractInternalSessionScorePreview($session);
                if ($this->isSupportingInformation($session)) {
                    $sessionScores[$label] = '-';
                } elseif ($preview) {
                    $sessionScores[$label] = $preview['score_text'];
                } elseif ($this->internalSessionUsesProgressMetric($session)) {
                    $answered = $this->countAnsweredQuestions($session->answers_json);
                    $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
                    $total = count($questions);
                    $sessionScores[$label] = $total > 0 ? $answered . '/' . $total : '-';
                } else {
                    $sessionScores[$label] = '-';
                }
            }

            $totalAnswered = 0;
            $totalQuestions = 0;
            foreach ($sessions as $session) {
                $totalAnswered += $this->countAnsweredQuestions($session->answers_json);
                $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
                $totalQuestions += count($questions);
            }
            $progress = $totalQuestions > 0 ? round(($totalAnswered / $totalQuestions) * 100) : 0;

            $rows[] = [
                'nama' => $attempt->participant_name ?? '-',
                'email' => $attempt->email ?? '-',
                'status' => $attempt->status ?? '-',
                'progress' => $progress . '%',
                'scores' => $sessionScores,
            ];
        }

        $moduleLabels = array_keys($moduleColumns);
        sort($moduleLabels);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ringkasan Peserta');

        $sheet->mergeCells('A1:' . $this->columnLetter(4 + count($moduleLabels)) . '1');
        $sheet->setCellValue('A1', 'RINGKASAN ASSESSMENT INTERNAL — ' . ($assessment->nama_assesment ?? ''));
        $this->applyStyle($sheet, 'A1', [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = array_merge(['No', 'Nama Peserta', 'Email', 'Status', 'Progress'], $moduleLabels);
        $col = 1;
        foreach ($headers as $header) {
            $cell = $this->columnLetter($col) . '3';
            $sheet->setCellValue($cell, $header);
            $this->applyStyle($sheet, $cell, $this->headerStyle());
            $col++;
        }

        $row = 4;
        foreach ($rows as $index => $item) {
            $col = 1;
            $values = [
                $index + 1,
                $item['nama'],
                $item['email'],
                $item['status'],
                $item['progress'],
            ];
            foreach ($moduleLabels as $label) {
                $values[] = $item['scores'][$label] ?? '-';
            }

            foreach ($values as $value) {
                $cell = $this->columnLetter($col) . $row;
                $sheet->setCellValue($cell, $value);
                $this->applyStyle($sheet, $cell, $this->bodyStyle());
                $col++;
            }
            $row++;
        }

        $lastCol = $this->columnLetter(count($headers));
        $sheet->getStyle('A3:' . $lastCol . ($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $link = $this->saveSpreadsheet(
            $spreadsheet,
            'Ringkasan_' . ($assessment->batch ?? 'assessment') . '_' . date('Ymd_His') . '.xlsx'
        );

        return [
            'message' => 'Export ringkasan Excel berhasil',
            'link' => $link,
        ];
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

        $employee = DB::table('master_karyawan')
            ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) ($attempt->email ?? '')))])
            ->first();

        if ($employee) {
            $employee = DB::table('master_karyawan as mk')
                ->leftJoin('master_jabatan as j', 'mk.id_jabatan', '=', 'j.id')
                ->leftJoin('master_divisi as d', 'mk.id_department', '=', 'd.id')
                ->leftJoin('master_cabang as c', 'mk.id_cabang', '=', 'c.id')
                ->where('mk.id', $employee->id)
                ->select(
                    'mk.*',
                    'j.nama_jabatan as nama_jabatan',
                    'd.nama_divisi as nama_divisi',
                    'c.nama_cabang as nama_cabang'
                )
                ->first();

            if ($employee) {
                $shioElemen = ShioElemenHelper::resolve(
                    $employee->tanggal_lahir ?? null,
                    $employee->shio ?? null,
                    $employee->elemen ?? null
                );
                $employee->shio = $shioElemen['shio'] ?? ($employee->shio ?? '-');
                $employee->elemen = $shioElemen['elemen'] ?? ($employee->elemen ?? '-');
            }
        }

        $sessions = DB::table('assessment_internal_sessions')
            ->where('assessment_internal_attempt_id', $attemptId)
            ->orderBy('session_order')
            ->get();

        return [
            'attempt' => $attempt,
            'assessment' => $assessment,
            'employee' => $employee,
            'sessions' => $sessions,
        ];
    }

    private function writeParticipantHeader(Worksheet $sheet, int $row, array $context): int
    {
        $attempt = $context['attempt'];
        $assessment = $context['assessment'];
        $employee = $context['employee'];

        $sheet->mergeCells('A' . $row . ':O' . $row);
        $sheet->setCellValue('A' . $row, 'LAPORAN HASIL ASSESSMENT INTERNAL — ' . strtoupper($assessment->nama_assesment ?? ''));
        $this->applyStyle($sheet, 'A' . $row, $this->mainTitleStyle());
        $row += 2;

        $leftFields = [
            ['NIK', $employee ? ($employee->nik_karyawan ?? '-') : '-'],
            ['Nama Karyawan', $attempt->participant_name ?? ($employee->nama_lengkap ?? '-')],
            ['Department', $employee ? ($employee->nama_divisi ?? '-') : '-'],
            ['Posisi', $employee ? ($employee->nama_jabatan ?? '-') : '-'],
            ['Cabang', $employee ? ($employee->nama_cabang ?? '-') : '-'],
            ['Shio', $employee ? ($employee->shio ?? '-') : '-'],
            ['Elemen', $employee ? ($employee->elemen ?? '-') : '-'],
        ];
        $rightFields = [
            ['Batch', $assessment->batch ?? '-'],
            ['Email Peserta', $attempt->email ?? '-'],
            ['Status', $attempt->status ?? '-'],
            ['Mulai', $this->formatDateTime($attempt->started_at)],
            ['Selesai', $this->formatDateTime($attempt->completed_at ?? $attempt->last_activity_at)],
            ['Diekspor', Carbon::now()->format('d/m/Y H:i')],
        ];

        $startRow = $row;
        foreach ($leftFields as $index => $field) {
            $current = $startRow + $index;
            $sheet->setCellValue('A' . $current, $field[0]);
            $sheet->setCellValue('C' . $current, ':');
            $sheet->setCellValue('D' . $current, $field[1]);
            $this->applyStyle($sheet, 'A' . $current, ['font' => ['bold' => true, 'size' => 11]]);
        }
        foreach ($rightFields as $index => $field) {
            $current = $startRow + $index;
            $sheet->setCellValue('H' . $current, $field[0]);
            $sheet->setCellValue('J' . $current, ':');
            $sheet->setCellValue('K' . $current, $field[1]);
            $this->applyStyle($sheet, 'H' . $current, ['font' => ['bold' => true, 'size' => 11]]);
        }

        return max($startRow + count($leftFields), $startRow + count($rightFields)) + 2;
    }

    private function writeSessionSummarySection(Worksheet $sheet, int $row, $sessions): int
    {
        $sheet->mergeCells('A' . $row . ':O' . $row);
        $sheet->setCellValue('A' . $row, 'RINGKASAN HASIL PER SESI');
        $this->applyStyle($sheet, 'A' . $row, $this->legacySectionHeaderStyle());
        $row++;

        $headers = ['No', 'Modul', 'Target Evaluasi', 'Skor / Progress', 'Status', 'Terjawab', 'Selesai'];
        $col = 1;
        foreach ($headers as $header) {
            $cell = $this->columnLetter($col) . $row;
            $sheet->setCellValue($cell, $header);
            $this->applyStyle($sheet, $cell, $this->headerStyle());
            $col++;
        }
        $row++;

        $headerRow = $row;
        $no = 1;
        $sessionCount = $sessions->count();
        foreach ($sessions as $session) {
            $preview = $this->extractInternalSessionScorePreview($session);
            $answered = $this->countAnsweredQuestions($session->answers_json);
            $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
            $total = count($questions);

            if ($this->isSupportingInformation($session)) {
                $metric = '-';
            } elseif ($preview) {
                $metric = $preview['score_text'];
            } elseif ($this->internalSessionUsesProgressMetric($session)) {
                $metric = $total > 0 ? round(($answered / $total) * 100, 0) . '% (' . $answered . '/' . $total . ')' : '-';
            } else {
                $metric = '-';
            }

            $values = [
                $no++,
                $session->category_name ?? '-',
                $this->resolveEvaluationTargetName($session) ?: '-',
                $metric,
                $session->status ?? '-',
                $total > 0 ? $answered . '/' . $total : '-',
                $this->formatDateTime($session->completed_at),
            ];

            $col = 1;
            foreach ($values as $value) {
                $cell = $this->columnLetter($col) . $row;
                $sheet->setCellValue($cell, $value);
                $this->applyStyle($sheet, $cell, $this->bodyStyle());
                $col++;
            }
            $row++;
        }

        if ($sessionCount > 0) {
            $this->borderRange($sheet, 'A' . $headerRow, 'G' . ($row - 1));
        }

        return $row + 1;
    }

    private function writeCognitiveAggregateSection(Worksheet $sheet, int $row, $sessions): int
    {
        $rows = [];
        foreach ($sessions as $session) {
            if (empty($session->result_json) || $this->internalSessionUsesProgressMetric($session)) {
                continue;
            }
            if ($this->isSupportingInformation($session) || $this->isEvaluationCategory($session->category_name ?? '')) {
                continue;
            }

            $result = json_decode($session->result_json, true) ?: [];
            $engine = strtolower(trim((string) ($result['engine'] ?? '')));
            if ($engine === 'scale_average') {
                $rows[] = [
                    'kategori' => $session->category_name ?? '-',
                    'benar' => '-',
                    'salah' => '-',
                    'persentase' => round((float) ($result['score'] ?? 0), 2),
                ];
                continue;
            }

            if (!in_array($engine, ['question_bank', 'mixed'], true)) {
                continue;
            }

            $total = (int) ($result['total_questions'] ?? 0);
            $correct = (int) ($result['correct_answers'] ?? 0);
            $answered = (int) ($result['answered'] ?? 0);
            $wrong = max(0, min($total, $answered) - $correct);
            $percent = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

            $rows[] = [
                'kategori' => $session->category_name ?? '-',
                'benar' => $correct,
                'salah' => $wrong,
                'persentase' => $percent,
            ];
        }

        if ($rows === []) {
            return $row;
        }

        $row = $this->writeLegacySectionTitle($sheet, $row, 'PENILAIAN KOGNITIF / IST');
        $headers = ['No', 'Kategori / Modul', 'Benar', 'Salah', 'Persentase (%)'];
        $row = $this->writeTableHeader($sheet, $row, $headers);

        $startRow = $row;
        foreach ($rows as $index => $item) {
            $this->writeTableRow($sheet, $row, [
                $index + 1,
                $item['kategori'],
                $item['benar'],
                $item['salah'],
                number_format($item['persentase'], 2) . '%',
            ]);
            $row++;
        }

        $this->borderRange($sheet, 'A' . ($startRow - 1), $this->columnLetter(count($headers)) . ($row - 1));

        return $row + 1;
    }

    private function writeEvaluationAggregateSections(Worksheet $sheet, int $row, $sessions): int
    {
        $groups = [
            'EMPLOYEE EVALUATION' => [
                'title' => 'PENILAIAN EMPLOYEE EVALUATION',
                'targetHeader' => 'Karyawan Dinilai',
            ],
            'SATISFACTION OF LEADER' => [
                'title' => 'PENILAIAN SATISFACTION OF LEADER',
                'targetHeader' => 'Atasan Dinilai',
            ],
            'MANAGEMENT EVALUATION' => [
                'title' => 'PENILAIAN MANAGEMENT EVALUATION',
                'targetHeader' => 'Management Dinilai',
            ],
            'EMPLOYEE SATISFACTION' => [
                'title' => 'PENILAIAN EMPLOYEE SATISFACTION',
                'targetHeader' => '-',
            ],
        ];

        foreach ($groups as $categoryKey => $meta) {
            $matched = $sessions->filter(function ($session) use ($categoryKey) {
                return $this->sessionCategoryKey($session) === $categoryKey && !empty($session->result_json);
            });

            if ($matched->isEmpty()) {
                continue;
            }

            $row = $this->writeLegacySectionTitle($sheet, $row, $meta['title']);
            $headers = ['No', 'Total Soal', $meta['targetHeader'], 'Total Jawaban', 'Persentase (%)'];
            $row = $this->writeTableHeader($sheet, $row, $headers);
            $startRow = $row;

            $totalPercent = 0;
            $count = 0;
            $no = 1;
            foreach ($matched as $session) {
                $result = json_decode($session->result_json, true) ?: [];
                $totalQuestions = (int) ($result['total_questions'] ?? 0);
                $totalValue = (float) ($result['total_value'] ?? 0);
                if ($totalValue <= 0 && isset($result['average_value'])) {
                    $totalValue = (float) $result['average_value'] * max(1, $totalQuestions);
                }
                $score = (float) ($result['score'] ?? 0);
                $target = $this->resolveEvaluationTargetName($session) ?: '-';

                $this->writeTableRow($sheet, $row, [
                    $no++,
                    $totalQuestions,
                    $target,
                    $totalValue > 0 ? round($totalValue, 2) : '-',
                    number_format($score, 2) . '%',
                ]);
                $totalPercent += $score;
                $count++;
                $row++;
            }

            if ($count > 1) {
                $sheet->mergeCells('A' . $row . ':D' . $row);
                $sheet->setCellValue('A' . $row, 'Rata-rata Persentase');
                $sheet->setCellValue('E' . $row, number_format($totalPercent / $count, 2) . '%');
                $this->applyStyle($sheet, 'A' . $row, ['font' => ['bold' => true]]);
                $row++;
            }

            $this->borderRange($sheet, 'A' . ($startRow - 1), 'E' . ($row - 1));
            $row++;
        }

        return $row;
    }

    private function writeDetailedSessionSections(Worksheet $sheet, int $row, $sessions): int
    {
        $sheet->mergeCells('A' . $row . ':O' . $row);
        $sheet->setCellValue('A' . $row, 'RINCIAN LENGKAP PER MODUL');
        $this->applyStyle($sheet, 'A' . $row, $this->legacySectionHeaderStyle());
        $row += 2;

        foreach ($sessions as $session) {
            if (empty($session->result_json)) {
                continue;
            }

            $result = json_decode($session->result_json, true) ?: [];
            $engine = strtolower(trim((string) ($result['engine'] ?? '')));
            $title = $this->internalSessionDisplayName($session);

            $sheet->mergeCells('A' . $row . ':O' . $row);
            $sheet->setCellValue('A' . $row, strtoupper($title));
            $this->applyStyle($sheet, 'A' . $row, $this->sectionTitleStyle());
            $row++;

            if ($engine === 'disc') {
                $row = $this->writeDiscSection($sheet, $row, $result);
            } elseif ($engine === 'papi_kostick') {
                $row = $this->writePapiSection($sheet, $row, $result);
            } else {
                $row = $this->writeGenericSection($sheet, $row, $session, $result);
            }

            $row++;
        }

        return $row;
    }

    private function writeDiscSection(Worksheet $sheet, int $row, array $result): int
    {
        $discDetail = $this->buildDiscDetail($result);
        $rawScores = $result['raw_scores'] ?? [];

        $row = $this->writeLegacySectionTitle($sheet, $row, 'PENILAIAN D.I.S.C.N');

        if (!empty($rawScores)) {
            $headers = ['Type', 'Graph I (Most)', 'Graph II (Least)', 'Graph III (Change)'];
            $row = $this->writeTableHeader($sheet, $row, $headers);
            $startRow = $row;
            foreach (['D', 'I', 'S', 'C', 'N'] as $type) {
                $values = $rawScores[$type] ?? [];
                $this->writeTableRow($sheet, $row, [
                    $type,
                    $values[1] ?? 0,
                    $values[2] ?? 0,
                    $values[3] ?? 0,
                ]);
                $row++;
            }
            $this->borderRange($sheet, 'A' . ($startRow - 1), 'D' . ($row - 1));
            $row++;
        }

        $chartPath = app(DiscProfileChartRenderer::class)->renderToFile($discDetail);
        if ($chartPath && is_file($chartPath)) {
            $sheet->setCellValue('A' . $row, 'Grafik Profil DISC');
            $this->applyStyle($sheet, 'A' . $row, ['font' => ['bold' => true]]);
            $row++;
            $drawing = new Drawing();
            $drawing->setPath($chartPath);
            $drawing->setCoordinates('B' . $row);
            $drawing->setHeight(220);
            $drawing->setWorksheet($sheet);
            $row += 14;
        }

        $row = $this->writeLegacySectionTitle($sheet, $row, 'PENILAIAN INTERPRETASI D.I.S.C.N');
        $profileTitles = [
            1 => 'Kepribadian di muka umum',
            2 => 'Kepribadian saat mendapat tekanan',
            3 => 'Kepribadian asli yang tersembunyi',
        ];

        foreach ($discDetail['profiles'] ?? [] as $profile) {
            $line = (int) ($profile['line'] ?? 0);
            $sheet->setCellValue('A' . $row, $profileTitles[$line] ?? $profile['title'] ?? ('Grafik ' . $line));
            $this->applyStyle($sheet, 'A' . $row, ['font' => ['bold' => true, 'size' => 11]]);
            $row++;
            $sheet->setCellValue('A' . $row, $profile['pattern'] ?? '-');
            $this->applyStyle($sheet, 'A' . $row, ['font' => ['bold' => true]]);
            $row++;

            foreach ($profile['behaviours'] ?? [] as $index => $behaviour) {
                $sheet->setCellValue('B' . $row, ($index + 1) . '. ' . $behaviour);
                $row++;
            }
            $row++;
        }

        if (!empty($discDetail['description'])) {
            $sheet->setCellValue('A' . $row, 'Deskripsi Kepribadian');
            $this->applyStyle($sheet, 'A' . $row, ['font' => ['bold' => true]]);
            $row++;
            $sheet->mergeCells('A' . $row . ':H' . ($row + 3));
            $sheet->setCellValue('A' . $row, $discDetail['description']);
            $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true);
            $row += 5;
        }

        if (!empty($discDetail['jobs'])) {
            $sheet->setCellValue('A' . $row, 'Job Match');
            $this->applyStyle($sheet, 'A' . $row, ['font' => ['bold' => true]]);
            $row++;
            foreach ($discDetail['jobs'] as $index => $job) {
                $sheet->setCellValue('B' . $row, ($index + 1) . '. ' . $job);
                $row++;
            }
        }

        return $row + 1;
    }

    private function writePapiSection(Worksheet $sheet, int $row, array $result): int
    {
        $papi = $this->buildPapiDetail($result);
        $row = $this->writeLegacySectionTitle($sheet, $row, 'PENILAIAN KOSTICK PAPI');
        $headers = ['No', 'Sikap & Gaya Kerja', 'Kode', 'Skor', 'Interpretasi'];
        $col = 1;
        foreach ($headers as $header) {
            $cell = $this->columnLetter($col) . $row;
            $sheet->setCellValue($cell, $header);
            $this->applyStyle($sheet, $cell, $this->headerStyle());
            $col++;
        }
        $row++;

        $startRow = $row;
        foreach ($papi['rows'] ?? [] as $item) {
            $values = [$item['no'], $item['role'], $item['code'], $item['score'], $item['interpretation']];
            $col = 1;
            foreach ($values as $value) {
                $cell = $this->columnLetter($col) . $row;
                $sheet->setCellValue($cell, $value);
                $this->applyStyle($sheet, $cell, $this->bodyStyle());
                $col++;
            }
            $row++;
        }

        if ($row > $startRow) {
            $sheet->getStyle('A' . ($startRow - 1) . ':E' . ($row - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        return $row;
    }

    private function writeGenericSection(Worksheet $sheet, int $row, $session, array $result): int
    {
        $supportingInformation = $this->isSupportingInformation($session);
        $summary = $this->buildSessionResultSummary($session, $result);
        foreach ($supportingInformation ? [] : ($summary['items'] ?? []) as $item) {
            $sheet->setCellValue('A' . $row, $item['label'] ?? '-');
            $sheet->setCellValue('B' . $row, $item['value'] ?? '-');
            $this->applyStyle($sheet, 'A' . $row, ['font' => ['bold' => true]]);
            $row++;
        }
        $row++;

        $headers = $supportingInformation
            ? ['No', 'Pernyataan', 'Jawaban', 'Status Pengisian']
            : ['No', 'Pertanyaan', 'Jawaban', 'Status', 'Kunci / Catatan'];
        $row = $this->writeTableHeader($sheet, $row, $headers);

        $startRow = $row;
        foreach ($this->buildQuestionReview($session, $result) as $item) {
            $status = '-';
            if ($item['unanswered'] ?? false) {
                $status = 'Kosong';
            } elseif ($supportingInformation) {
                $status = 'Terisi';
            } elseif (($item['is_scale'] ?? false) === true) {
                $status = 'Skala';
            } elseif ($item['is_correct'] === true) {
                $status = 'Benar';
            } elseif ($item['is_correct'] === false) {
                $status = 'Salah';
            }

            $note = $item['answer_key_text'] ?? null;
            if (($item['is_scale'] ?? false) && $note === null) {
                $note = 'Nilai skala: ' . ($item['answer_text'] ?? '-');
            }

            $values = [
                $item['no'] ?? '-',
                $item['text'] ?? '-',
                $item['answer_text'] ?? '-',
                $status,
            ];
            if (!$supportingInformation) {
                $values[] = $note ?? '-';
            }
            $this->writeTableRow($sheet, $row, $values);
            $sheet->getStyle('B' . $row . ':E' . $row)->getAlignment()->setWrapText(true);
            $row++;
        }

        if ($row > $startRow) {
            $this->borderRange($sheet, 'A' . ($startRow - 1), 'E' . ($row - 1));
        }

        return $row;
    }

    private function isSupportingInformation($session): bool
    {
        return strtoupper(trim((string) ($session->category_name ?? ''))) === 'INFORMASI PENDUKUNG';
    }

    private function saveSpreadsheet(Spreadsheet $spreadsheet, string $fileName): string
    {
        $dir = base_path('public/' . self::OUTPUT_DIR);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Gagal membuat folder export.');
        }

        $safeName = preg_replace('/[^\w\-]/', '_', $fileName);
        if (substr(strtolower($safeName), -5) !== '.xlsx') {
            $safeName .= '.xlsx';
        }

        $fullPath = $dir . '/' . $safeName;
        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        return self::OUTPUT_DIR . '/' . $safeName;
    }

    private function buildFileName($batch, $participantName): string
    {
        $safeBatch = preg_replace('/[^\w\-]/', '_', (string) $batch);
        $safeName = preg_replace('/[^\w\-]/', '_', (string) $participantName);

        return 'Eval_Internal_' . $safeBatch . '_' . $safeName . '_' . date('Ymd_His');
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

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    private function applyStyle(Worksheet $sheet, string $cell, array $style): void
    {
        $sheet->getStyle($cell)->applyFromArray($style);
    }

    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE2E8F0'],
            ],
        ];
    }

    private function bodyStyle(): array
    {
        return [
            'font' => ['size' => 11],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ];
    }

    private function sectionTitleStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF1D4ED8']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEFF6FF'],
            ],
        ];
    }

    private function mainTitleStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 18],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
    }

    private function legacySectionHeaderStyle(): array
    {
        return [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2883FA'],
            ],
        ];
    }

    private function writeLegacySectionTitle(Worksheet $sheet, int $row, string $title): int
    {
        $sheet->mergeCells('A' . $row . ':O' . $row);
        $sheet->setCellValue('A' . $row, $title);
        $this->applyStyle($sheet, 'A' . $row, $this->legacySectionHeaderStyle());

        return $row + 2;
    }

    private function writeTableHeader(Worksheet $sheet, int $row, array $headers): int
    {
        $col = 1;
        foreach ($headers as $header) {
            $cell = $this->columnLetter($col) . $row;
            $sheet->setCellValue($cell, $header);
            $this->applyStyle($sheet, $cell, $this->headerStyle());
            $col++;
        }

        return $row + 1;
    }

    private function writeTableRow(Worksheet $sheet, int $row, array $values): void
    {
        $col = 1;
        foreach ($values as $value) {
            $cell = $this->columnLetter($col) . $row;
            $sheet->setCellValue($cell, $value);
            $this->applyStyle($sheet, $cell, $this->bodyStyle());
            $col++;
        }
    }

    private function borderRange(Worksheet $sheet, string $fromCell, string $toCell): void
    {
        $sheet->getStyle($fromCell . ':' . $toCell)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function sessionCategoryKey($session): string
    {
        return strtoupper(trim((string) ($session->category_name ?? '')));
    }

    private function isEvaluationCategory(string $categoryName): bool
    {
        return in_array(strtoupper(trim($categoryName)), [
            'EMPLOYEE EVALUATION',
            'SATISFACTION OF LEADER',
            'MANAGEMENT EVALUATION',
            'EMPLOYEE SATISFACTION',
        ], true);
    }
}
