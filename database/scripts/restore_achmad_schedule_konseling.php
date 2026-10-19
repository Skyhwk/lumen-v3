<?php

/**
 * Kembalikan 2 permohonan Achmad Maulana Alawiyyy ke tab Schedule Konseling.
 *
 * Usage:
 *   php database/scripts/restore_achmad_schedule_konseling.php
 *   php database/scripts/restore_achmad_schedule_konseling.php --run
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->boot();

use App\Models\SalaryAdjustmentAssessment;
use App\Models\SalaryAdjustmentAssessmentSession;
use App\Services\InternalTypingTestService;
use App\Services\SalaryAdjustmentAssessmentEngine;
use App\Services\SalaryAdjustmentWorkflowService;
use Illuminate\Support\Facades\DB;

$execute = in_array('--run', $argv, true);
$requestIds = [6, 7];
$employeeName = 'Achmad Maulana Alawiyyy';

$rows = DB::table('salary_adjustment_requests as sar')
    ->join('master_karyawan as k', 'k.id', '=', 'sar.employee_id')
    ->whereIn('sar.id', $requestIds)
    ->where('k.nama_lengkap', $employeeName)
    ->select('sar.id', 'sar.no_document', 'sar.status')
    ->get();

if ($rows->count() !== 2) {
    echo "Harus ada 2 permohonan untuk {$employeeName} (id 6 & 7). Ditemukan: {$rows->count()}\n";
    exit(1);
}

echo "Target restore Schedule Konseling:\n";
foreach ($rows as $row) {
    echo "  - id={$row->id} {$row->no_document} status={$row->status}\n";
}

if (!$execute) {
    echo "\nDry-run. Jalankan dengan --run\n";
    exit(0);
}

DB::connection('mysql')->transaction(function () use ($requestIds) {
    DB::table('salary_adjustment_counselings')->whereIn('request_id', $requestIds)->delete();

    DB::table('salary_adjustment_requests')->whereIn('id', $requestIds)->update([
        'status' => SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_COMPLETED,
        'is_active' => 1,
        'updated_by' => 'restore_schedule_konseling',
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $engine = app(SalaryAdjustmentAssessmentEngine::class);
    $typingService = new InternalTypingTestService();

    foreach ($requestIds as $requestId) {
        $assessment = SalaryAdjustmentAssessment::where('request_id', $requestId)->first();
        if (!$assessment) {
            echo "Skip request {$requestId}: assessment tidak ada.\n";
            continue;
        }

        $sessions = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)
            ->orderBy('session_order')
            ->get();

        foreach ($sessions as $session) {
            $questions = is_array($session->questions_json)
                ? $session->questions_json
                : (json_decode($session->questions_json ?: '[]', true) ?: []);
            $answers = is_array($session->answers_json)
                ? $session->answers_json
                : (json_decode($session->answers_json ?: '{}', true) ?: []);

            $changed = false;
            foreach ($questions as $question) {
                $qid = (string) ($question['id'] ?? '');
                if ($qid === '') {
                    continue;
                }

                $isTyping = ($question['type'] ?? '') === 'typing_test'
                    || InternalTypingTestService::isTypingCategoryName($session->category_name);

                if (!$isTyping) {
                    continue;
                }

                $current = $answers[$qid] ?? null;
                if ($current === null || !is_array($current) || !isset($current['typed_words'])) {
                    $answers[$qid] = [
                        'typed_words' => ['marketing', 'tim', 'deadline', 'parameter', 'ini'],
                        'duration_ms' => (int) (($session->duration_minutes ?: 1) * 60 * 1000),
                        'target_word_count' => count($question['words'] ?? []),
                        'restored' => true,
                    ];
                    $changed = true;
                }
            }

            if ($changed) {
                $session->answers_json = $answers;
                $session->save();
            }

            if (InternalTypingTestService::isTypingCategoryName($session->category_name)) {
                $result = $typingService->scoreSession($questions, $answers);
                $result['status'] = 'completed';
                $session->answers_json = $answers;
                $session->result_json = $result;
                $session->status = 'completed';
                $session->completed_at = $session->completed_at ?: date('Y-m-d H:i:s');
                $session->save();
            }
        }

        $assessment->refresh();
        $allSessions = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)->get();
        $scores = $allSessions->map(function ($item) {
            $result = is_array($item->result_json)
                ? $item->result_json
                : (json_decode($item->result_json ?: '{}', true) ?: []);

            return array_key_exists('score', $result) ? (float) $result['score'] : null;
        })->filter(function ($score) {
            return $score !== null;
        });

        $assessment->attempt_status = 'completed';
        $assessment->is_link_active = 0;
        $assessment->completed_at = $assessment->completed_at ?: date('Y-m-d H:i:s');
        $assessment->total_score = $scores->isNotEmpty() ? round($scores->avg(), 2) : 0;
        $assessment->result_json = [
            'engine' => 'multi_module',
            'sessions' => $allSessions->map(function ($item) {
                $sessionResult = is_array($item->result_json)
                    ? $item->result_json
                    : (json_decode($item->result_json ?: '{}', true) ?: []);

                return [
                    'name' => $item->category_name,
                    'score' => $sessionResult['score'] ?? null,
                    'status' => $item->status,
                ];
            })->values()->all(),
            'score' => $assessment->total_score,
        ];
        $assessment->save();

        echo "OK request {$requestId} assessment {$assessment->id} total_score={$assessment->total_score}\n";
    }
});

echo "Selesai. Refresh Konseling Karyawan → Schedule Konseling (periode 2026).\n";
