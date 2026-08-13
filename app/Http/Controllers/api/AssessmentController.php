<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\RecruitmentStatusService;

class AssessmentController extends Controller
{
    public function overview(Request $request)
    {
        $recruitment = $this->recruitment($request->token);
        $attempt = $this->ensureAttempt($recruitment);
        $categories = DB::table('assessment_sessions')
            ->where('assessment_attempt_id', $attempt->id)
            ->orderBy('session_order')
            ->get(['category_name as name', 'question_count', 'duration_minutes']);
        $hasStartedSession = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->whereNotNull('started_at')->exists();
        $status = $hasStartedSession ? $this->stateFor($attempt)['status'] : 'ready';
        return response()->json(['status' => $status, 'expires_at' => Carbon::parse($recruitment->created_at)->addDays(2), 'categories' => $categories, 'preview_questions' => $this->previewQuestions($attempt->id, 3)]);
    }

    public function start(Request $request)
    {
        $recruitment = $this->recruitment($request->token);
        $attempt = $this->ensureAttempt($recruitment);
        return DB::transaction(function () use ($attempt) {
            $attempt = DB::table('assessment_attempts')->where('id', $attempt->id)->lockForUpdate()->first();
            if (!$attempt) {
                abort(404, 'Assessment tidak ditemukan.');
            }
            $activeSession = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->where('status', 'in_progress')->first();
            if (!$activeSession) {
                $this->startNextSession($attempt->id);
            }

            return response()->json($this->stateFor($attempt));
        });
    }

    public function preview(Request $request)
    {
        $attempt = $this->ensureAttempt($this->recruitment($request->token));
        return response()->json(['questions' => $this->previewQuestions($attempt->id, 3), 'duration_seconds' => 300]);
    }

    public function state(Request $request)
    {
        $attempt = DB::table('assessment_attempts')->where('token', $request->token)->first();
        if (!$attempt) {
            abort(404, 'Assessment tidak ditemukan.');
        }
        return response()->json($this->stateFor($attempt));
    }

    public function answer(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $attempt = DB::table('assessment_attempts')->where('token', $request->token)->lockForUpdate()->first();
            if (!$attempt) {
                abort(404, 'Assessment tidak ditemukan.');
            }
            $state = $this->stateFor($attempt);
            if (($state['status'] ?? null) !== 'in_progress' || (string) $state['question']['id'] !== (string) $request->question_id) return response()->json($state, 409);
            $session = DB::table('assessment_sessions')->where('id', $state['session']['id'])->lockForUpdate()->first();
            $answers = json_decode($session->answers_json ?: '{}', true) ?: [];
            $question = collect(json_decode($session->questions_json ?: '[]', true) ?: [])->firstWhere('id', (string) $request->question_id);
            if ($question && $question['type'] === 'disc') {
                $most = $request->answer['P'] ?? null;
                $least = $request->answer['K'] ?? null;
                if ($most === null || $least === null || (string) $most === (string) $least) return response()->json(['message' => 'Pilih pernyataan yang paling dan paling tidak menggambarkan diri Anda.'], 422);
                if (!array_key_exists($request->question_id, $answers)) $answers[$request->question_id] = ['P' => (string) $most, 'K' => (string) $least];
            } elseif (!array_key_exists($request->question_id, $answers)) {
                $answers[$request->question_id] = is_array($request->answer) ? array_values($request->answer) : [$request->answer];
            }
            DB::table('assessment_sessions')->where('id', $session->id)->update(['answers_json' => json_encode($answers), 'updated_at' => Carbon::now()]);
            return response()->json($this->stateFor($attempt));
        });
    }

    public function proctoringEvent(Request $request)
    {
        $allowed = ['camera_granted', 'camera_denied', 'camera_stopped', 'tab_hidden', 'tab_visible', 'window_blur', 'window_focus', 'data_processing_consent'];
        if (!in_array($request->event, $allowed, true)) return response()->json(['message' => 'Event tidak valid.'], 422);
        $attempt = DB::table('assessment_attempts')->where('token', $request->token)->first();
        if (!$attempt) {
            abort(404, 'Assessment tidak ditemukan.');
        }
        $meta = json_decode($attempt->proctoring_meta ?: '[]', true) ?: [];
        $meta[] = ['event' => $request->event, 'at' => Carbon::now()->toDateTimeString()];
        DB::table('assessment_attempts')->where('id', $attempt->id)->update(['proctoring_meta' => json_encode($meta), 'updated_at' => Carbon::now()]);
        return response()->json(['status' => true]);
    }

    public function proctoringStatus(Request $request)
    {
        $attempt = DB::table('assessment_attempts')->where('token', $request->token)->first();
        if (!$attempt) {
            abort(404, 'Assessment tidak ditemukan.');
        }

        $events = collect(json_decode($attempt->proctoring_meta ?: '[]', true) ?: []);
        $latestLeaveEvent = $events->reverse()->first(function ($event) {
            return in_array($event['event'] ?? null, ['tab_hidden', 'window_blur'], true);
        });

        return response()->json(['latest_leave_event' => $latestLeaveEvent]);
    }

    private function ensureAttempt($recruitment)
    {
        return DB::transaction(function () use ($recruitment) {
            DB::table('new_recruitment')->where('id', $recruitment->id)->lockForUpdate()->first();
            $attempt = DB::table('assessment_attempts')->where('recruitment_id', $recruitment->id)->lockForUpdate()->first();
            if ($attempt) {
                $hasStartedSession = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->whereNotNull('started_at')->exists();
                $sessions = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->orderBy('session_order')->get();
                $expectedSessions = $this->sessionDefinitions($recruitment);
                $sessionDefinitions = $sessions->map(function ($session) {
                    return [$session->category_name, (int) $session->question_count, (int) $session->duration_minutes];
                })->all();
                if (!$hasStartedSession && $sessionDefinitions !== $expectedSessions) {
                    DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->delete();
                    $this->createSessions($attempt->id, Carbon::now(), $recruitment);
                } else {
                    $pendingSessions = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->where('status', 'pending')->get();
                    foreach ($pendingSessions as $session) {
                        $category = DB::table('question_categories')->where('id', $session->question_category_id)->first();
                        if ($category && ($session->category_name !== $category->name || (int) $session->question_count !== (int) $category->question_count || (int) $session->duration_minutes !== (int) $category->duration_minutes)) {
                            $items = $this->sessionQuestions($category);
                            DB::table('assessment_sessions')->where('id', $session->id)->update([
                                'category_name' => $category->name,
                                'question_count' => $category->question_count,
                                'duration_minutes' => $category->duration_minutes,
                                'questions_json' => json_encode($items),
                                'updated_at' => Carbon::now()
                            ]);
                        }
                    }
                }
                return $attempt;
            }

            $now = Carbon::now();
            $attemptId = DB::table('assessment_attempts')->insertGetId([
                'recruitment_id' => $recruitment->id,
                'token' => $recruitment->token,
                'token_created_at' => $recruitment->created_at,
                'token_expires_at' => Carbon::parse($recruitment->created_at)->addDays(2),
                'started_at' => $now,
                'status' => 'in_progress',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->createSessions($attemptId, $now, $recruitment);

            return DB::table('assessment_attempts')->where('id', $attemptId)->first();
        });
    }

    private function createSessions($attemptId, $now, $recruitment)
    {
        $categories = $this->assessmentCategories()->get();
        foreach ($categories as $index => $category) {
            $items = $this->sessionQuestions($category);
            if (count($items) !== (int) $category->question_count) {
                throw new \RuntimeException('Soal untuk sesi ' . $category->name . ' belum mencukupi.');
            }
            DB::table('assessment_sessions')->insert(['assessment_attempt_id' => $attemptId, 'question_category_id' => $category->id, 'session_order' => $index + 1, 'category_name' => $category->name, 'question_count' => $category->question_count, 'duration_minutes' => $category->duration_minutes, 'questions_json' => json_encode($items), 'answers_json' => json_encode(new \stdClass()), 'result_json' => null, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        }

        $userConfig = $this->userAssessmentConfig($recruitment);
        if (!$userConfig) {
            return;
        }

        $items = $this->userSessionQuestions($userConfig);
        if (count($items) !== (int) $userConfig->question_count) {
            throw new \RuntimeException('Soal assessment user untuk ' . $userConfig->owner_karyawan . ' belum mencukupi.');
        }

        DB::table('assessment_sessions')->insert([
            'assessment_attempt_id' => $attemptId,
            'question_category_id' => null,
            'session_order' => $categories->count() + 1,
            'category_name' => 'Assessment User',
            'question_count' => $userConfig->question_count,
            'duration_minutes' => $userConfig->duration_minutes,
            'questions_json' => json_encode($items),
            'answers_json' => json_encode(new \stdClass()),
            'result_json' => null,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function sessionDefinitions($recruitment)
    {
        $definitions = $this->assessmentCategories()->get()->map(function ($category) {
            return [$category->name, (int) $category->question_count, (int) $category->duration_minutes];
        })->all();

        $userConfig = $this->userAssessmentConfig($recruitment);
        if ($userConfig) {
            $definitions[] = ['Assessment User', (int) $userConfig->question_count, (int) $userConfig->duration_minutes];
        }

        return $definitions;
    }

    private function userAssessmentConfig($recruitment)
    {
        $personnelRequest = DB::table('personnel_requests')->where('id', $recruitment->personnel_request_id)->first();
        if (!$personnelRequest || (int) ($personnelRequest->use_user_assessment ?? 0) !== 1 || empty($personnelRequest->created_by)) {
            return null;
        }

        $config = DB::table('user_assessment_configs')
            ->where('owner_karyawan', $personnelRequest->created_by)
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            throw new \RuntimeException('Konfigurasi assessment user untuk ' . $personnelRequest->created_by . ' belum tersedia atau belum aktif.');
        }

        return $config;
    }

    private function userSessionQuestions($config)
    {
        return DB::table('questions')
            ->where('owner_karyawan', $config->owner_karyawan)
            ->where('is_active', 1)
            ->where('question_type', 'single_choice')
            ->inRandomOrder()
            ->limit($config->question_count)
            ->get()
            ->values()
            ->map(function ($question, $key) {
                $options = DB::table('question_options')->where('question_id', $question->id)->orderBy('option_order')->get()->map(function ($option) {
                    return ['id' => (string) $option->id, 'text' => $option->option_text, 'is_correct' => (bool) $option->is_correct];
                })->all();

                return ['id' => (string) $question->id, 'source' => 'user_question_bank', 'order' => $key + 1, 'type' => $question->question_type, 'text' => $question->question_text, 'image' => json_decode($question->question_image ?: '[]', true), 'options' => $options, 'answer_key' => collect($options)->where('is_correct', true)->pluck('id')->values()->all(), 'scoring_type' => $question->scoring_type];
            })->all();
    }

    private function previewQuestions($attemptId, $limit)
    {
        $selectedIds = DB::table('assessment_sessions')->where('assessment_attempt_id', $attemptId)->pluck('questions_json')->flatMap(function ($questions) {
            return collect(json_decode($questions ?: '[]', true) ?: [])->pluck('id');
        })->filter()->unique()->values()->all();
        $query = DB::table('questions')->where('is_active', 1)->whereIn('question_type', ['single_choice', 'multiple_choice']);
        if (!empty($selectedIds)) {
            $query->whereNotIn('id', $selectedIds);
        }
        return $query->inRandomOrder()->limit($limit)->get()->map(function ($question, $index) {
            return ['id' => $question->id, 'order' => $index + 1, 'type' => $question->question_type, 'text' => $question->question_text, 'image' => json_decode($question->question_image ?: '[]', true) ?: [], 'options' => DB::table('question_options')->where('question_id', $question->id)->orderBy('option_order')->get(['id', 'option_text'])->map(function ($option) { return ['id' => (string) $option->id, 'text' => $option->option_text]; })->all()];
        })->values()->all();
    }

    private function assessmentCategories()
    {
        return DB::table('question_categories')
            ->where('is_active', 1)
            ->where('is_show', 1)
            ->orderByRaw("CASE WHEN UPPER(name) = 'DISC' THEN 1 WHEN UPPER(name) IN ('KOSTICK PAPI', 'PAPI KOSTICK') THEN 2 ELSE 3 END")
            ->orderBy('id');
    }

    private function sessionQuestions($category)
    {
        if (strtoupper($category->name) === 'DISC') {
            return $this->discQuestions();
        }
        if (in_array(strtoupper($category->name), ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return $this->papiQuestions();
        }

        $questions = DB::table('questions')
            ->where('question_category_id', $category->id)
            ->where('is_active', 1)
            ->whereIn('question_type', ['single_choice', 'multiple_choice', 'scale'])
            ->inRandomOrder()
            ->limit($category->question_count)
            ->get();

        return $questions->values()->map(function ($question, $key) {
            $options = DB::table('question_options')->where('question_id', $question->id)->orderBy('option_order')->get()->map(function ($option) {
                return ['id' => (string) $option->id, 'text' => $option->option_text, 'is_correct' => (bool) $option->is_correct];
            })->all();
            if ($question->question_type === 'scale') {
                $scale = DB::table('scale_types')->where('id', $question->scale_type_id)->first();
                $options = collect(json_decode($scale->options ?? '[]', true) ?: [])->map(function ($option, $optionKey) {
                    return ['id' => 'scale-' . ($option['value'] ?? $optionKey), 'text' => $option['label'] ?? $option['value'], 'is_correct' => false];
                })->all();
            }
            return ['id' => (string) $question->id, 'source' => 'question_bank', 'order' => $key + 1, 'type' => $question->question_type, 'text' => $question->question_text, 'image' => json_decode($question->question_image ?: '[]', true), 'options' => $options, 'answer_key' => collect($options)->where('is_correct', true)->pluck('id')->values()->all(), 'scoring_type' => $question->scoring_type];
        })->all();
    }

    private function discQuestions()
    {
        return DB::table('soal_psikotes')->where('kategori_soal', 'DISC')->orderBy('id')->get()->values()->map(function ($question, $key) {
            $prompt = json_decode($question->pertanyaan ?: '{}', true) ?: [];
            $answer = json_decode($question->jawaban ?: '{}', true) ?: [];
            $options = array_values($prompt['data'] ?? []);
            return ['id' => (string) $question->id, 'source' => 'disc', 'order' => $key + 1, 'type' => 'disc', 'text' => 'Pilih satu pernyataan yang paling dan paling tidak menggambarkan diri Anda', 'options' => collect($options)->map(function ($text, $optionKey) { return ['id' => (string) $optionKey, 'text' => $text]; })->all(), 'answer_map' => $answer['data'] ?? ['P' => [], 'K' => []]];
        })->all();
    }

    private function papiQuestions()
    {
        return DB::table('soal_psikotes')->whereIn('kategori_soal', ['KOSTICK PAPI', 'PAPI KOSTICK'])->orderBy('id')->get()->values()->map(function ($question, $key) {
            $prompt = json_decode($question->pertanyaan ?: '{}', true) ?: [];
            $answer = json_decode($question->jawaban ?: '{}', true) ?: [];
            $options = array_values($answer['data'] ?? []);
            return ['id' => (string) $question->id, 'source' => 'papi_kostick', 'order' => $key + 1, 'type' => 'single_choice', 'text' => '', 'options' => collect($options)->map(function ($text, $optionKey) {
                $cleanText = preg_replace('/^[a-zA-Z][\)\.]\s*/', '', $text);
                return ['id' => (string) $optionKey, 'text' => $cleanText];
            })->all(), 'answer_map' => array_values($answer['value'] ?? [])];
        })->all();
    }

    private function recruitment($token, $lock = false)
    {
        $query = DB::table('new_recruitment')->where('token', $token); if ($lock) $query->lockForUpdate(); $row = $query->first();
        if (!$row || Carbon::now()->greaterThanOrEqualTo(Carbon::parse($row->created_at)->addDays(2))) abort(410, 'Link assessment tidak valid atau sudah kedaluwarsa.');
        return $row;
    }

    private function stateFor($attempt)
    {
        if (!$attempt || Carbon::now()->greaterThanOrEqualTo(Carbon::parse($attempt->token_expires_at))) { if ($attempt) DB::table('assessment_attempts')->where('id', $attempt->id)->update(['status' => 'expired']); return ['status' => 'expired', 'message' => 'Waktu akses assessment sudah habis.']; }
        $session = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->whereIn('status', ['pending', 'in_progress'])->orderBy('session_order')->first();
        if (!$session) {
            $completedAt = Carbon::now();
            DB::table('assessment_attempts')->where('id', $attempt->id)->update(['status' => 'completed', 'completed_at' => $completedAt]);
            (new RecruitmentStatusService())->update($attempt->recruitment_id, 'screening', $completedAt);
            
            // Auto trigger AI evaluation on assessment completion
            $this->processAiMatching($attempt->id, $attempt->recruitment_id);

            return ['status' => 'completed', 'message' => 'Assessment selesai.'];
        }
        if ($session->status === 'pending') {
            return ['status' => 'waiting', 'message' => 'Sesi berikutnya siap dimulai saat Anda sudah siap.', 'session' => ['id' => $session->id, 'name' => $session->category_name, 'order' => $session->session_order, 'duration_minutes' => $session->duration_minutes, 'question_count' => $session->question_count]];
        }
        $questions = json_decode($session->questions_json ?: '[]', true) ?: []; $answers = json_decode($session->answers_json ?: '{}', true) ?: [];
        if (Carbon::now()->greaterThanOrEqualTo(Carbon::parse($session->expires_at))) { foreach ($questions as $question) if (!array_key_exists($question['id'], $answers)) $answers[$question['id']] = null; $this->finishSession($session, $answers, 'expired'); return $this->stateFor($attempt); }
        $question = collect($questions)->first(function ($item) use ($answers) { return !array_key_exists($item['id'], $answers); });
        if (!$question) { $this->finishSession($session, $answers, 'completed'); return $this->stateFor($attempt); }
        unset($question['answer_key'], $question['answer_map']); foreach ($question['options'] as &$option) unset($option['is_correct']);
        return ['status' => 'in_progress', 'session' => ['id' => $session->id, 'name' => $session->category_name, 'order' => $session->session_order, 'duration_minutes' => $session->duration_minutes, 'expires_at' => $session->expires_at], 'question' => array_merge($question, ['total' => $session->question_count])];
    }

    private function finishSession($session, array $answers, $status)
    {
        $result = $this->scoreSession($session, $answers);
        $result['status'] = $status;
        $result['scored_at'] = Carbon::now()->toDateTimeString();

        DB::table('assessment_sessions')->where('id', $session->id)->update([
            'answers_json' => json_encode($answers),
            'result_json' => json_encode($result),
            'status' => $status,
            'completed_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function scoreSession($session, array $answers)
    {
        $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
        if (strtoupper($session->category_name) === 'DISC') {
            return $this->scoreDisc($questions, $answers);
        }
        if (in_array(strtoupper($session->category_name), ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return $this->scorePapi($questions, $answers);
        }

        $answered = 0;
        $correct = 0;
        foreach ($questions as $question) {
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

        $totalQuestions = count($questions);
        return ['engine' => 'question_bank', 'answered' => $answered, 'total_questions' => $totalQuestions, 'correct_answers' => $correct, 'score' => $totalQuestions ? round(($correct / $totalQuestions) * 100, 2) : 0];
    }

    private function scorePapi(array $questions, array $answers)
    {
        $roleIds = [];
        foreach ($questions as $question) {
            $answer = $answers[$question['id']] ?? null;
            $choice = is_array($answer) ? reset($answer) : $answer;
            $roleId = $question['answer_map'][(int) $choice] ?? null;
            if ($choice !== null && $roleId !== null) {
                $roleIds[] = (int) $roleId;
            }
        }

        $scores = array_count_values($roleIds);
        $roles = DB::table('papi_roles')->join('papi_aspects', 'papi_aspects.id', '=', 'papi_roles.aspect_id')->select('papi_roles.id', 'papi_roles.code', 'papi_roles.role', 'papi_aspects.id as aspect_id', 'papi_aspects.aspect as aspect_name')->get()->keyBy('id');
        $aspects = [];
        foreach ($scores as $roleId => $score) {
            $role = $roles[$roleId] ?? null;
            if (!$role) {
                continue;
            }
            $rule = DB::table('papi_rules')->where('role_id', $roleId)->where('low_value', '<=', $score)->where('high_value', '>=', $score)->first();
            if (!isset($aspects[$role->aspect_id])) {
                $aspects[$role->aspect_id] = ['aspect_id' => $role->aspect_id, 'aspect_name' => $role->aspect_name, 'roles' => []];
            }
            $aspects[$role->aspect_id]['roles'][] = ['role_id' => (int) $role->id, 'role_code' => $role->code, 'role_description' => $role->role, 'score' => $score, 'interpretation' => $rule->interprestation ?? 'Interpretasi tidak ditemukan'];
        }

        return ['engine' => 'papi_kostick', 'answered' => count($roleIds), 'total_questions' => count($questions), 'aspects' => array_values($aspects)];
    }

    private function scoreDisc(array $questions, array $answers)
    {
        $most = [];
        $least = [];
        foreach ($questions as $question) {
            $answer = $answers[$question['id']] ?? null;
            if (!is_array($answer)) {
                continue;
            }
            $mostValue = $question['answer_map']['P'][(int) ($answer['P'] ?? -1)] ?? null;
            $leastValue = $question['answer_map']['K'][(int) ($answer['K'] ?? -1)] ?? null;
            if ($mostValue) {
                $most[] = $mostValue;
            }
            if ($leastValue) {
                $least[] = $leastValue;
            }
        }

        $mostCounts = array_count_values($most);
        $leastCounts = array_count_values($least);
        $result = [];
        foreach (['D', 'I', 'S', 'C', 'N'] as $aspect) {
            $result[$aspect] = [1 => $mostCounts[$aspect] ?? 0, 2 => $leastCounts[$aspect] ?? 0, 3 => $aspect === 'N' ? 0 : (($mostCounts[$aspect] ?? 0) - ($leastCounts[$aspect] ?? 0))];
        }

        $legacyScorer = app()->make(\App\Http\Controllers\api\EvaluasiKaryawanController::class);
        $profiles = [];
        foreach ([1, 2, 3] as $line) {
            $legacyResult = $legacyScorer->getPattern($result, $line);
            $profiles[] = ['line' => $line, 'scores' => (array) $legacyResult[0], 'pattern' => isset($legacyResult[1]) && is_object($legacyResult[1]) ? $legacyResult[1]->toArray() : null];
        }

        return ['engine' => 'disc', 'answered' => count($most), 'total_questions' => count($questions), 'raw_scores' => $result, 'profiles' => $profiles];
    }

    private function startNextSession($attemptId)
    {
        $session = DB::table('assessment_sessions')->where('assessment_attempt_id', $attemptId)->where('status', 'pending')->orderBy('session_order')->lockForUpdate()->first();
        if (!$session) {
            return null;
        }

        $start = Carbon::now();
        DB::table('assessment_sessions')->where('id', $session->id)->update([
            'status' => 'in_progress',
            'started_at' => $start,
            'expires_at' => $start->copy()->addMinutes($session->duration_minutes),
            'updated_at' => $start,
        ]);

        return $session->id;
    }

    /**
     * Public API endpoint to fetch or re-trigger AI Matching calculation
     */
    public function getAiPayload(Request $request)
    {
        $recruitmentId = $request->input('recruitment_id') ?? $request->input('id');
        if (!$recruitmentId) {
            return response()->json(['message' => 'ID recruitment tidak ditemukan'], 400);
        }

        $attempt = DB::table('assessment_attempts')->where('recruitment_id', $recruitmentId)->orderBy('id', 'desc')->first();
        if (!$attempt) {
            return response()->json(['message' => 'Assessment attempt tidak ditemukan untuk kandidat ini'], 404);
        }

        $payloadData = $this->processAiMatching($attempt->id, $recruitmentId);
        if (!$payloadData) {
            return response()->json(['message' => 'Gagal memproses data AI payload'], 500);
        }

        return response()->json([
            'status' => 'success',
            'data' => $payloadData
        ], 200);
    }

    /**
     * Build AI Assessment Payload & Send to Ollama AI Server
     */
    public function processAiMatching($attemptId, $recruitmentId)
    {
        try {
            $recruitment = DB::table('new_recruitment')->where('id', $recruitmentId)->first();
            if (!$recruitment) {
                return null;
            }

            // 1. Data Personal Request (Posisi Target & Kebutuhan)
            $personnelRequest = DB::table('personnel_requests')
                ->leftJoin('master_jabatan', 'master_jabatan.id', '=', 'personnel_requests.posisi')
                ->leftJoin('master_divisi', 'master_divisi.id', '=', 'personnel_requests.divisi')
                ->leftJoin('master_cabang', 'master_cabang.id', '=', 'personnel_requests.lokasi_penempatan_cabang')
                ->where('personnel_requests.id', $recruitment->personnel_request_id)
                ->select(
                    'personnel_requests.*',
                    'master_jabatan.nama_jabatan as nama_posisi',
                    'master_divisi.nama_divisi as nama_divisi',
                    'master_cabang.nama_cabang as nama_cabang'
                )
                ->first();

            // 2. Data Diri Pelamar / Candidate
            $candidateEducations = DB::table('candidate_educations')
                ->where('new_recruitment_id', $recruitmentId)
                ->get();
            
            $candidateWorkExps = DB::table('candidate_work_experiences')
                ->where('new_recruitment_id', $recruitmentId)
                ->get();

            // 3. Hasil Assessment (Seluruh Test)
            $sessions = DB::table('assessment_sessions')
                ->where('assessment_attempt_id', $attemptId)
                ->get();

            // Format Riwayat Pendidikan Kandidat
            $eduList = [];
            if ($candidateEducations->isNotEmpty()) {
                foreach ($candidateEducations as $edu) {
                    $eduList[] = implode(' ', array_filter([$edu->jenjang_pendidikan, $edu->jurusan, $edu->nama_institusi]));
                }
            } elseif (!empty($recruitment->pendidikan)) {
                $rawEdu = json_decode($recruitment->pendidikan, true) ?: [];
                foreach ($rawEdu as $e) {
                    if (is_array($e)) {
                        $eduList[] = implode(' ', array_filter([$e['jenjang'] ?? '', $e['jurusan'] ?? '', $e['institusi'] ?? '']));
                    }
                }
            }
            $candidateEduStr = implode('; ', array_filter($eduList)) ?: ($recruitment->pendidikan_terakhir ?? 'Pendidikan tidak diisi');

            // Format Pengalaman Kerja Kandidat
            $expList = [];
            if ($candidateWorkExps->isNotEmpty()) {
                foreach ($candidateWorkExps as $exp) {
                    $expList[] = implode(' ', array_filter([$exp->posisi_terakhir, 'di', $exp->nama_perusahaan]));
                }
            } elseif (!empty($recruitment->pengalaman_kerja)) {
                $rawExp = json_decode($recruitment->pengalaman_kerja, true) ?: [];
                foreach ($rawExp as $x) {
                    if (is_array($x)) {
                        $expList[] = implode(' ', array_filter([$x['posisi_kerja'] ?? '', 'di', $x['nama_perusahaan'] ?? '']));
                    }
                }
            }
            $candidateExpStr = implode('; ', array_filter($expList)) ?: 'Belum ada pengalaman kerja';

            // Format Skill & Keahlian Kandidat
            $skillsList = [];
            if (!empty($recruitment->keahlian)) {
                $rawSkills = json_decode($recruitment->keahlian, true) ?: [];
                foreach ($rawSkills as $s) {
                    if (is_array($s) && isset($s['keahlianSkill'])) {
                        $skillsList[] = $s['keahlianSkill'];
                    }
                }
            }
            $candidateSkillsStr = implode(', ', $skillsList) ?: ($recruitment->skill_wajib ?? 'Skill tidak spesifik');

            // Format Hasil Seluruh Test Assessment (Original JSON untuk DISC & PAPI Kostick)
            $structuredSessions = [];
            $assessmentSummary = [];
            foreach ($sessions as $session) {
                $res = json_decode($session->result_json, true) ?: [];
                $answers = json_decode($session->answers_json, true) ?: [];

                $structuredSessions[] = [
                    'session_id' => $session->id,
                    'category_name' => $session->category_name,
                    'session_order' => $session->session_order,
                    'status' => $session->status,
                    'answers' => $answers,
                    'result_json' => $res, // Original JSON result
                ];

                if (isset($res['engine']) && $res['engine'] === 'disc') {
                    $assessmentSummary[] = 'Hasil DISC (Original JSON): ' . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } elseif (isset($res['engine']) && $res['engine'] === 'papi_kostick') {
                    $assessmentSummary[] = 'Hasil PAPI Kostick (Original JSON): ' . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } elseif (isset($res['score'])) {
                    $assessmentSummary[] = $session->category_name . ': ' . $res['score'] . '/100';
                }
            }
            $testSummaryStr = implode(' | ', $assessmentSummary) ?: 'Assessment Selesai';

            // Clean HTML tags from requirement fields for plain text prompt
            $cleanEduReq = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($personnelRequest->pendidikan ?? '')))) ?: 'Kualifikasi pendidikan tidak ditentukan';
            $cleanExpReq = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($personnelRequest->pengalaman_kerja ?? '')))) ?: 'Pengalaman tidak ditentukan';
            $cleanSkillsReq = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($personnelRequest->skill_wajib ?? '')))) ?: 'Skill wajib tidak ditentukan';
            $posisiReq = $personnelRequest->nama_posisi ?? ($personnelRequest->posisi ?? 'Jabatan tidak ditentukan');
            $minScoreReq = $personnelRequest->minimum_matching ?? 75;

            // Formulate Prompt String for AI Ollama Server
            $promptText = sprintf(
                "Kandidat: %s, %s, %s, %s. Posisi: %s, %s, %s, %s, technical test minimal %s. Berikan skor kecocokan.",
                $candidateEduStr,
                $candidateExpStr,
                $candidateSkillsStr,
                $testSummaryStr,
                $posisiReq,
                $cleanEduReq,
                $cleanExpReq,
                $cleanSkillsReq,
                $minScoreReq
            );

            // Construct exact cURL Payload for Ollama AI
            $aiPayload = [
                'model' => 'intilab-ats',
                'prompt' => $promptText,
                'stream' => false,
                'think' => false,
            ];

            // Full Structured Data Object with Original JSON
            $structuredData = [
                'personnel_request' => $personnelRequest,
                'candidate_profile' => [
                    'id' => $recruitment->id,
                    'nama_lengkap' => $recruitment->nama_lengkap,
                    'email' => $recruitment->email,
                    'no_telepon' => $recruitment->no_telepon,
                    'pendidikan' => $candidateEduStr,
                    'pengalaman_kerja' => $candidateExpStr,
                    'skill' => $candidateSkillsStr,
                ],
                'assessment_results' => $structuredSessions,
                'ai_payload' => $aiPayload,
            ];

            // Log AI Prompt & Payload
            \Illuminate\Support\Facades\Log::info("=== AI MATCHING GENERATE PROMPT ===", [
                'recruitment_id' => $recruitmentId,
                'kandidat' => $recruitment->nama_lengkap ?? '',
                'prompt' => $promptText,
                'payload' => $aiPayload,
            ]);

            // Send to Ollama AI Server via HTTP POST cURL
            $aiResult = $this->sendToOllamaAi($aiPayload);

            // Log AI Response
            \Illuminate\Support\Facades\Log::info("=== AI MATCHING GENERATE RESPONSE ===", [
                'recruitment_id' => $recruitmentId,
                'kandidat' => $recruitment->nama_lengkap ?? '',
                'ai_response' => $aiResult,
            ]);

            if ($aiResult && !empty($aiResult['response'])) {
                $responseStr = $aiResult['response'];
                
                // Extract score integer if present
                $matchingScore = null;
                if (preg_match('/(\d{1,3})\s*%?/', $responseStr, $matches)) {
                    $scoreVal = (int) $matches[1];
                    if ($scoreVal <= 100) {
                        $matchingScore = $scoreVal;
                    }
                }

                $updateData = [
                    'updated_at' => Carbon::now(),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_response')) {
                    $updateData['ai_matching_response'] = $responseStr;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_data')) {
                    $updateData['ai_matching_data'] = json_encode($structuredData);
                }
                if ($matchingScore !== null) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'matching_score')) {
                        $updateData['matching_score'] = $matchingScore;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'nilai_kecocokan')) {
                        $updateData['nilai_kecocokan'] = $matchingScore;
                    }

                    // Automatic rejection if matching score is below minimum requirement
                    if ($minScoreReq !== null && $matchingScore < $minScoreReq) {
                        $rejectReason = "Matching score AI ({$matchingScore}%) di bawah minimum requirement ({$minScoreReq}%).";

                        (new \App\Services\RecruitmentStatusService())->update($recruitmentId, 'rejected', Carbon::now());

                        $updateData['status'] = 'rejected';
                        $updateData['rejected_by'] = 'System AI';
                        $updateData['rejected_at'] = Carbon::now();
                        $updateData['alasan_reject'] = $rejectReason;

                        $this->triggerAutoRejectNotifications($recruitmentId, $rejectReason);

                        \Illuminate\Support\Facades\Log::info("=== AI AUTO REJECT CANDIDATE ===", [
                            'recruitment_id' => $recruitmentId,
                            'matching_score' => $matchingScore,
                            'minimum_matching' => $minScoreReq,
                            'reason' => $rejectReason
                        ]);
                    }
                }

                DB::table('new_recruitment')->where('id', $recruitmentId)->update($updateData);
                $structuredData['ai_response'] = $aiResult;
            }

            return $structuredData;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('processAiMatching Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send rejection notifications (Email & WhatsApp) when candidate is auto-rejected
     */
    private function triggerAutoRejectNotifications($recruitmentId, $reason)
    {
        try {
            $applicant = \App\Models\NewRecruitment::with('personalRequest.masterJabatan')->find($recruitmentId);
            if (!$applicant) return;

            $posisiName = optional($applicant->personalRequest)->masterJabatan->nama_jabatan 
                ?? ($applicant->posisi_di_lamar ?? 'Positions');

            $dataArray = (object) [
                'nama_lengkap' => $applicant->nama_lengkap,
                'posisi_di_lamar' => $posisiName,
                'nama_jabatan' => $posisiName,
                'alasan_reject' => $reason,
                'hrd_name' => 'System AI',
            ];

            if (!empty($applicant->email)) {
                $bodyEmail = \App\Services\GenerateMessageAtsEmail::bodyEmailRejectKandidat($dataArray);
                \App\Services\SendEmail::where('to', $applicant->email)
                    ->where('subject', 'Informasi Hasil Seleksi - PT Inti Surya Laboratorium')
                    ->where('body', $bodyEmail)
                    ->where('karyawan', 'System AI')
                    ->noReply()
                    ->send();
            }

            $phone = $applicant->no_telepon ?: ($applicant->no_hp ?? null);
            if (!empty($phone)) {
                $waObj = new \App\Services\GenerateMessageAtsWhatsapp($dataArray);
                $waMessage = $waObj->RejectedCandidateSelection();

                $sendWa = new \App\Services\SendWhatsapp($phone, $waMessage);
                $sendWa->send();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('triggerAutoRejectNotifications Error: ' . $e->getMessage());
        }
    }

    /**
     * Send HTTP POST request to Ollama AI Server (http://10.88.209.240:11434/api/generate)
     */
    private function sendToOllamaAi(array $payload)
    {
        try {
            $ch = curl_init('http://10.88.209.240:11434/api/generate');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                \Illuminate\Support\Facades\Log::error('sendToOllamaAi cURL Error: ' . $curlError);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }
            
            \Illuminate\Support\Facades\Log::warning("sendToOllamaAi HTTP Status {$httpCode}: " . $response);
            return null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('sendToOllamaAi Exception: ' . $e->getMessage());
            return null;
        }
    }
}
