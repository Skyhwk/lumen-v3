<?php

namespace App\Services;

use App\Models\SalaryAdjustmentAssessment;
use App\Models\SalaryAdjustmentAssessmentSession;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryAdjustmentAssessmentEngine
{
    public function buildQuestionsForCategory(object $category): array
    {
        $name = strtoupper(trim((string) $category->name));

        if ($name === 'DISC') {
            return $this->discQuestions();
        }

        if (in_array($name, ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return $this->papiQuestions();
        }

        $questions = DB::table('questions')
            ->where('question_category_id', $category->id)
            ->where('is_active', 1)
            ->whereIn('question_type', ['single_choice', 'multiple_choice', 'scale'])
            ->inRandomOrder()
            ->limit((int) $category->question_count)
            ->get();

        return $questions->values()->map(function ($question, $key) {
            $options = DB::table('question_options')
                ->where('question_id', $question->id)
                ->orderBy('option_order')
                ->get()
                ->map(function ($option) {
                    return [
                        'id' => (string) $option->id,
                        'text' => $option->option_text,
                        'is_correct' => (bool) $option->is_correct,
                    ];
                })->all();

            if ($question->question_type === 'scale') {
                $scale = DB::table('scale_types')->where('id', $question->scale_type_id)->first();
                $options = $scale ? ScaleScoringService::buildScaleOptions($scale) : [];
            }

            $payload = [
                'id' => (string) $question->id,
                'source' => 'question_bank',
                'order' => $key + 1,
                'type' => $question->question_type,
                'text' => $question->question_text,
                'image' => json_decode($question->question_image ?: '[]', true),
                'options' => $options,
                'answer_key' => collect($options)->where('is_correct', true)->pluck('id')->values()->all(),
                'scoring_type' => $question->scoring_type,
            ];

            if ($question->question_type === 'scale' && !empty($options)) {
                $values = collect($options)->pluck('value');
                $payload['scale_min'] = (float) $values->min();
                $payload['scale_max'] = (float) $values->max();
            }

            return $payload;
        })->all();
    }

    public function overview(SalaryAdjustmentAssessment $assessment): array
    {
        if (!$assessment->is_link_active && $assessment->attempt_status === 'completed') {
            return [
                'status' => 'used',
                'message' => 'Link assessment sudah digunakan.',
            ];
        }

        if (!$assessment->is_link_active) {
            return [
                'status' => 'inactive',
                'message' => 'Link assessment tidak aktif.',
            ];
        }

        $sessions = $assessment->sessions()->orderBy('session_order')->get();
        $currentSession = $this->resolveCurrentSession($assessment, $sessions);
        $request = $assessment->request;
        $employee = $request
            ? DB::table('master_karyawan')->where('id', $request->employee_id)->first()
            : null;

        $status = 'ready';
        if ($assessment->attempt_status === 'completed') {
            $status = 'completed';
        } elseif ($assessment->attempt_status === 'in_progress') {
            $status = 'in_progress';
        }

        $moduleNames = $sessions->pluck('category_name')->filter()->values()->all();
        $totalQuestions = (int) $sessions->sum('question_count');
        $completedModules = $sessions->whereIn('status', ['completed', 'expired'])->count();
        $categories = $sessions->map(function ($session) {
            $questions = is_array($session->questions_json)
                ? $session->questions_json
                : (json_decode($session->questions_json ?: '[]', true) ?: []);

            return [
                'name' => $session->category_name,
                'question_count' => (int) $session->question_count,
                'available_question_count' => count($questions),
                'can_start' => !empty($questions),
                'duration_minutes' => (int) $session->duration_minutes,
                'has_time_limit' => (int) $session->duration_minutes > 0,
                'status' => $session->status,
                'order' => (int) $session->session_order,
            ];
        })->values()->all();

        $nextPendingSession = $sessions->firstWhere('status', 'pending');
        $canStart = in_array($assessment->attempt_status, ['pending', 'in_progress'], true);
        if ($nextPendingSession && empty($nextPendingSession->questions_json)) {
            $canStart = false;
        }

        return [
            'status' => $status,
            'categories' => $categories,
            'assessment' => [
                'category_name' => ($currentSession ? $currentSession->category_name : null) ?? ($moduleNames[0] ?? '-'),
                'module_names' => $moduleNames,
                'module_count' => $sessions->count(),
                'completed_modules' => $completedModules,
                'question_count' => $totalQuestions,
                'duration_minutes' => (int) $assessment->duration_minutes,
                'has_time_limit' => (bool) $assessment->has_time_limit,
            ],
            'employee_name' => $employee->nama_lengkap ?? '-',
            'can_start' => $canStart,
            'start_blocked_reason' => $canStart
                ? null
                : 'Sesi belum dapat dimulai karena soal belum tersedia.',
        ];
    }

    public function start(SalaryAdjustmentAssessment $assessment): array
    {
        if (!$assessment->is_link_active) {
            return ['error' => 'Link assessment tidak aktif.', 'code' => 403];
        }

        if ($assessment->attempt_status === 'completed') {
            return ['error' => 'Link assessment sudah digunakan.', 'code' => 403];
        }

        return DB::connection('mysql')->transaction(function () use ($assessment) {
            $assessment = SalaryAdjustmentAssessment::where('id', $assessment->id)->lockForUpdate()->first();
            $session = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw("FIELD(status, 'in_progress', 'pending')")
                ->orderBy('session_order')
                ->lockForUpdate()
                ->first();

            if (!$session) {
                return ['error' => 'Sesi assessment tidak ditemukan.', 'code' => 404];
            }

            $now = Carbon::now();
            if ($assessment->attempt_status === 'pending') {
                $assessment->attempt_status = 'in_progress';
                $assessment->started_at = $now;
                $assessment->save();

        SalaryAdjustmentRequest::where('id', $assessment->request_id)->update([
            'status' => SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_IN_PROGRESS,
            'updated_by' => 'system',
        ]);
            }

            if ($session->status === 'pending') {
                $session->status = 'in_progress';
                $session->started_at = $now;
                if ((int) $session->duration_minutes > 0) {
                    $session->expires_at = $now->copy()->addMinutes((int) $session->duration_minutes);
                }
                $session->save();
            }

            return ['state' => $this->statePayload($assessment)];
        });
    }

    public function state(SalaryAdjustmentAssessment $assessment): array
    {
        if (!$assessment->is_link_active && $assessment->attempt_status === 'completed') {
            return ['status' => 'used', 'message' => 'Link assessment sudah digunakan.'];
        }

        return $this->statePayload($assessment);
    }

    public function answer(SalaryAdjustmentAssessment $assessment, string $questionId, $answer): array
    {
        $current = $assessment->fresh();
        if ($current->attempt_status === 'completed' || !$current->is_link_active) {
            $payload = $this->statePayload($current);
            if (($payload['status'] ?? '') === 'completed') {
                return ['state' => $payload];
            }

            return ['error' => 'Link assessment sudah digunakan.', 'code' => 403];
        }

        return DB::connection('mysql')->transaction(function () use ($assessment, $questionId, $answer) {
            $assessment = SalaryAdjustmentAssessment::where('id', $assessment->id)->lockForUpdate()->first();
            $session = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();

            if (!$session) {
                $payload = $this->statePayload($assessment);
                if (($payload['status'] ?? '') === 'completed') {
                    return ['state' => $payload];
                }

                return ['error' => 'Tidak ada sesi aktif.', 'code' => 409];
            }

            if ($this->sessionIsExpired($session)) {
                return ['state' => $this->advanceExpiredSession($assessment, $session), 'code' => 403];
            }

            $activeQuestion = $this->resolveActiveQuestion($session);
            if (!$activeQuestion) {
                if (!in_array($session->status, ['completed', 'expired'], true)) {
                    $answers = is_array($session->answers_json) ? $session->answers_json : [];
                    $this->finishSession($assessment, $session, $answers, 'completed');
                }

                return ['state' => $this->statePayload($assessment->fresh())];
            }

            if ((string) ($activeQuestion['id'] ?? '') !== (string) $questionId) {
                return [
                    'state' => $this->buildInProgressState($assessment, $session, $activeQuestion),
                    'code' => 409,
                ];
            }

            $answers = is_array($session->answers_json) ? $session->answers_json : [];
            $questions = is_array($session->questions_json) ? $session->questions_json : [];
            $question = collect($questions)->firstWhere('id', (string) $questionId);

            if ($question && ($question['type'] ?? '') === 'disc') {
                $most = $answer['P'] ?? null;
                $least = $answer['K'] ?? null;
                if ($most === null || $least === null || (string) $most === (string) $least) {
                    return ['error' => 'Pilih pernyataan paling dan paling tidak menggambarkan diri Anda.', 'code' => 422];
                }
                if (!array_key_exists($questionId, $answers)) {
                    $answers[$questionId] = ['P' => (string) $most, 'K' => (string) $least];
                }
            } elseif (!array_key_exists($questionId, $answers)) {
                $answers[$questionId] = is_array($answer) ? array_values($answer) : [$answer];
            }

            $session->answers_json = $answers;
            $session->save();

            $session->refresh();
            $assessment->refresh();

            if (!$this->resolveActiveQuestion($session)) {
                $this->finishSession($assessment, $session, $answers, 'completed');

                return ['state' => $this->statePayload($assessment->fresh())];
            }

            $nextQuestion = $this->resolveActiveQuestion($session);

            return ['state' => $this->buildInProgressState($assessment, $session, $nextQuestion)];
        });
    }

    private function statePayload(SalaryAdjustmentAssessment $assessment): array
    {
        if ($assessment->attempt_status === 'completed') {
            return [
                'status' => 'completed',
                'message' => 'Assessment selesai.',
                'score' => (float) ($assessment->total_score ?? 0),
            ];
        }

        $sessions = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)
            ->orderBy('session_order')
            ->get();

        $session = $this->resolveCurrentSession($assessment, $sessions);
        if (!$session) {
            return ['status' => 'error', 'message' => 'Sesi tidak ditemukan.'];
        }

        if ($session->status === 'pending') {
            $hasCompleted = $sessions->contains(function ($item) {
                return in_array($item->status, ['completed', 'expired'], true);
            });

            return [
                'status' => 'waiting',
                'message' => $hasCompleted
                    ? 'Modul selesai. Tekan mulai untuk melanjutkan modul berikutnya.'
                    : 'Tekan mulai untuk memulai assessment.',
                'session' => $this->sessionMeta($session, $assessment),
            ];
        }

        return $this->buildState($assessment, $session);
    }

    private function buildState(SalaryAdjustmentAssessment $assessment, SalaryAdjustmentAssessmentSession $session): array
    {
        if ($assessment->attempt_status === 'completed') {
            return [
                'status' => 'completed',
                'message' => 'Assessment selesai.',
                'score' => (float) ($assessment->total_score ?? 0),
            ];
        }

        if ($session->status === 'pending') {
            return [
                'status' => 'waiting',
                'message' => 'Tekan mulai untuk memulai assessment.',
                'session' => $this->sessionMeta($session, $assessment),
            ];
        }

        $questions = is_array($session->questions_json) ? $session->questions_json : [];
        $answers = is_array($session->answers_json) ? $session->answers_json : [];

        if ($session->expires_at && Carbon::now()->greaterThanOrEqualTo(Carbon::parse($session->expires_at))) {
            foreach ($questions as $question) {
                if (!array_key_exists($question['id'], $answers)) {
                    $answers[$question['id']] = null;
                }
            }
            if (!in_array($session->status, ['completed', 'expired'], true)) {
                $this->finishSession($assessment, $session, $answers, 'expired');
            }

            return $this->statePayload($assessment->fresh());
        }

        $question = collect($questions)->first(function ($item) use ($answers) {
            return !array_key_exists($item['id'], $answers);
        });

        if (!$question) {
            if (!in_array($session->status, ['completed', 'expired'], true)) {
                $this->finishSession($assessment, $session, $answers, 'completed');
            }

            return $this->statePayload($assessment->fresh());
        }

        return [
            'status' => 'in_progress',
            'session' => $this->sessionMeta($session, $assessment),
            'question' => $this->sanitizeQuestion($question, count($answers), count($questions)),
            'answered_count' => count($answers),
        ];
    }

    private function finishSession(
        SalaryAdjustmentAssessment $assessment,
        SalaryAdjustmentAssessmentSession $session,
        array $answers,
        string $status
    ): void {
        $result = $this->scoreSession($session, $answers);
        $result['status'] = $status;

        $session->answers_json = $answers;
        $session->result_json = $result;
        $session->status = $status === 'completed' ? 'completed' : 'expired';
        $session->completed_at = Carbon::now();
        $session->save();

        $hasPendingSession = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingSession) {
            return;
        }

        $allSessions = SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)->get();
        $scores = $allSessions
            ->map(function ($item) {
                $result = is_array($item->result_json) ? $item->result_json : [];

                return array_key_exists('score', $result) ? (float) $result['score'] : null;
            })
            ->filter(function ($score) {
                return $score !== null;
            });

        $assessment->attempt_status = 'completed';
        $assessment->completed_at = Carbon::now();
        $assessment->total_score = $scores->isNotEmpty() ? round($scores->avg(), 2) : ($result['score'] ?? 0);
        $assessment->result_json = [
            'engine' => 'multi_module',
            'sessions' => $allSessions->map(function ($item) {
                $sessionResult = is_array($item->result_json) ? $item->result_json : [];

                return [
                    'name' => $item->category_name,
                    'score' => isset($sessionResult['score']) ? $sessionResult['score'] : null,
                    'status' => $item->status,
                ];
            })->values()->all(),
            'score' => $assessment->total_score,
        ];
        $assessment->is_link_active = false;
        $assessment->link_deactivated_at = Carbon::now();
        $assessment->save();

        $requestRecord = SalaryAdjustmentRequest::find($assessment->request_id);
        $fromStatus = $requestRecord ? $requestRecord->status : SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_IN_PROGRESS;
        $nextStatus = $requestRecord
            ? (new EmployeeAdjustmentWorkflowResolver())->resolvePostAssessmentCompletedStatus($requestRecord)
            : SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_COMPLETED;
        $skipCounseling = $nextStatus === SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION;

        SalaryAdjustmentRequest::where('id', $assessment->request_id)->update([
            'status' => $nextStatus,
            'updated_by' => 'system',
        ]);

        SalaryAdjustmentLogService::log(
            $assessment->request_id,
            $fromStatus,
            $nextStatus,
            'assessment_completed',
            null,
            'system',
            $skipCounseling
                ? 'Assessment karyawan selesai — langsung ke Evaluasi Final (lewati konseling)'
                : 'Assessment karyawan selesai'
        );
    }

    private function scoreSession(SalaryAdjustmentAssessmentSession $session, array $answers): array
    {
        $questions = is_array($session->questions_json) ? $session->questions_json : [];
        $name = strtoupper(trim((string) $session->category_name));

        if ($name === 'DISC' || in_array($name, ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return [
                'engine' => strtolower(str_replace(' ', '_', $name)),
                'answered' => count(array_filter($answers, function ($v) {
                    return $v !== null;
                })),
                'total_questions' => count($questions),
                'score' => count($questions)
                    ? round((count(array_filter($answers, function ($v) {
                        return $v !== null;
                    })) / count($questions)) * 100, 2)
                    : 0,
            ];
        }

        $scaleQuestions = collect($questions)->filter(function ($q) {
            return (($q['type'] ?? '') === 'scale');
        });
        if ($scaleQuestions->count() === count($questions) && $scaleQuestions->isNotEmpty()) {
            return ScaleScoringService::scoreQuestions($questions, $answers);
        }

        $correct = 0;
        $answered = 0;
        foreach ($questions as $question) {
            if (($question['type'] ?? '') === 'scale') {
                continue;
            }
            $answer = $answers[$question['id']] ?? null;
            if ($answer === null) {
                continue;
            }
            $answered++;
            $given = is_array($answer) ? array_values($answer) : [$answer];
            $key = array_values($question['answer_key'] ?? []);
            sort($given);
            sort($key);
            if ($key && $given === $key) {
                $correct++;
            }
        }

        $total = count($questions);

        return [
            'engine' => 'question_bank',
            'answered' => $answered,
            'total_questions' => $total,
            'correct_answers' => $correct,
            'score' => $total ? round(($correct / $total) * 100, 2) : 0,
        ];
    }

    private function sessionIsExpired(SalaryAdjustmentAssessmentSession $session): bool
    {
        return $session->expires_at
            && Carbon::now()->greaterThanOrEqualTo(Carbon::parse($session->expires_at));
    }

    private function advanceExpiredSession(
        SalaryAdjustmentAssessment $assessment,
        SalaryAdjustmentAssessmentSession $session
    ): array {
        if (!in_array($session->status, ['completed', 'expired'], true)) {
            $questions = is_array($session->questions_json) ? $session->questions_json : [];
            $answers = is_array($session->answers_json) ? $session->answers_json : [];

            foreach ($questions as $question) {
                if (!array_key_exists($question['id'], $answers)) {
                    $answers[$question['id']] = null;
                }
            }

            $this->finishSession($assessment, $session, $answers, 'expired');
        }

        return $this->statePayload($assessment->fresh());
    }

    private function resolveActiveQuestion(SalaryAdjustmentAssessmentSession $session): ?array
    {
        if ($session->status !== 'in_progress') {
            return null;
        }

        $questions = is_array($session->questions_json) ? $session->questions_json : [];
        $answers = is_array($session->answers_json) ? $session->answers_json : [];
        $question = collect($questions)->first(function ($item) use ($answers) {
            return !array_key_exists($item['id'], $answers);
        });

        if (!$question) {
            return null;
        }

        return $this->sanitizeQuestion($question, count($answers), count($questions));
    }

    private function sanitizeQuestion(array $question, int $answeredCount, int $totalQuestions): array
    {
        $safeQuestion = $question;
        unset($safeQuestion['answer_key'], $safeQuestion['answer_map']);
        if (!empty($safeQuestion['options'])) {
            foreach ($safeQuestion['options'] as &$option) {
                unset($option['is_correct']);
            }
        }

        return array_merge($safeQuestion, [
            'index' => $answeredCount + 1,
            'total' => $totalQuestions,
        ]);
    }

    private function buildInProgressState(
        SalaryAdjustmentAssessment $assessment,
        SalaryAdjustmentAssessmentSession $session,
        array $question
    ): array {
        $answers = is_array($session->answers_json) ? $session->answers_json : [];

        return [
            'status' => 'in_progress',
            'session' => $this->sessionMeta($session, $assessment),
            'question' => $question,
            'answered_count' => count($answers),
        ];
    }

    private function sessionMeta(SalaryAdjustmentAssessmentSession $session, SalaryAdjustmentAssessment $assessment): array
    {
        return [
            'name' => $session->category_name,
            'order' => $session->session_order,
            'duration_minutes' => (int) $session->duration_minutes,
            'has_time_limit' => (int) $session->duration_minutes > 0,
            'expires_at' => $session->expires_at,
            'question_count' => (int) $session->question_count,
        ];
    }

    private function resolveCurrentSession(SalaryAdjustmentAssessment $assessment, $sessions = null)
    {
        $sessions = $sessions ?? SalaryAdjustmentAssessmentSession::where('assessment_id', $assessment->id)
            ->orderBy('session_order')
            ->get();

        $inProgress = $sessions->firstWhere('status', 'in_progress');
        if ($inProgress) {
            return $inProgress;
        }

        return $sessions->firstWhere('status', 'pending');
    }

    private function discQuestions(): array
    {
        return DB::table('soal_psikotes')->where('kategori_soal', 'DISC')->orderBy('id')->get()->values()->map(function ($question, $key) {
            $prompt = json_decode($question->pertanyaan ?: '{}', true) ?: [];
            $answer = json_decode($question->jawaban ?: '{}', true) ?: [];
            $options = array_values($prompt['data'] ?? []);

            return [
                'id' => (string) $question->id,
                'source' => 'disc',
                'order' => $key + 1,
                'type' => 'disc',
                'text' => 'Pilih satu pernyataan yang paling dan paling tidak menggambarkan diri Anda',
                'options' => collect($options)->map(function ($text, $optionKey) {
                    return ['id' => (string) $optionKey, 'text' => $text];
                })->all(),
                'answer_map' => $answer['data'] ?? ['P' => [], 'K' => []],
            ];
        })->all();
    }

    private function papiQuestions(): array
    {
        return DB::table('soal_psikotes')->whereIn('kategori_soal', ['KOSTICK PAPI', 'PAPI KOSTICK'])->orderBy('id')->get()->values()->map(function ($question, $key) {
            $answer = json_decode($question->jawaban ?: '{}', true) ?: [];
            $options = array_values($answer['data'] ?? []);

            return [
                'id' => (string) $question->id,
                'source' => 'papi_kostick',
                'order' => $key + 1,
                'type' => 'single_choice',
                'text' => '',
                'options' => collect($options)->map(function ($text, $optionKey) {
                    return ['id' => (string) $optionKey, 'text' => preg_replace('/^[a-zA-Z][\)\.]\s*/', '', $text)];
                })->all(),
                'answer_map' => array_values($answer['value'] ?? []),
            ];
        })->all();
    }
}
