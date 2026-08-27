<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\GetBawahan;
use App\Services\RecruitmentStatusService;
use App\Services\ScaleScoringService;
use App\Services\AtsNotificationService;
use App\Services\UserAssessmentCategoryService;

class AssessmentController extends Controller
{
    public function overview(Request $request)
    {
        $recruitment = $this->recruitment($request->token);
        if (!$recruitment) {
            return $this->expiredLinkResponse();
        }

        $attempt = $this->ensureAttempt($recruitment);
        $sessions = DB::table('assessment_sessions')
            ->where('assessment_attempt_id', $attempt->id)
            ->orderBy('session_order')
            ->get(['category_name', 'question_count', 'duration_minutes', 'question_category_id', 'expires_at', 'questions_json', 'status', 'session_order']);
        $categories = $sessions->map(function ($session) {
            $availableCount = count($this->sessionQuestionItems($session));

            return [
                'name' => $session->category_name,
                'question_count' => (int) $session->question_count,
                'available_question_count' => $availableCount,
                'can_start' => $this->sessionIsReady($session),
                'duration_minutes' => (int) $session->duration_minutes,
                'has_time_limit' => $this->sessionHasTimeLimit($session),
            ];
        })->values();
        $hasStartedSession = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->whereNotNull('started_at')->exists();
        $status = $hasStartedSession ? $this->stateFor($attempt)['status'] : 'ready';
        if ($status === 'expired') {
            return $this->expiredLinkResponse('Waktu akses assessment sudah habis.');
        }
        $nextPendingSession = $sessions->firstWhere('status', 'pending');
        $canStart = $nextPendingSession ? $this->sessionIsReady($nextPendingSession) : false;
        $userConfig = $this->userAssessmentConfig($recruitment);
        $personnelRequest = DB::table('personnel_requests')->where('id', $recruitment->personnel_request_id)->first();

        return response()->json([
            'status' => $status,
            'expires_at' => Carbon::parse($recruitment->created_at)->addDays(2),
            'categories' => $categories,
            'can_start' => $canStart,
            'start_blocked_reason' => $canStart ? null : $this->sessionStartBlockReason($nextPendingSession),
            'preview_questions' => $this->previewQuestions($attempt->id, 3),
            'has_user_assessment' => $userConfig !== null,
            'personnel_request_no' => $personnelRequest->no_request ?? null,
            'user_assessment' => $userConfig ? [
                'question_count' => (int) $userConfig->question_count,
                'duration_minutes' => (int) $userConfig->duration_minutes,
                'has_time_limit' => (bool) $userConfig->has_time_limit,
            ] : null,
        ]);
    }

    public function start(Request $request)
    {
        $recruitment = $this->recruitment($request->token);
        if (!$recruitment) {
            return $this->expiredLinkResponse();
        }

        $attempt = $this->ensureAttempt($recruitment);
        return DB::transaction(function () use ($attempt) {
            $attempt = DB::table('assessment_attempts')->where('id', $attempt->id)->lockForUpdate()->first();
            if (!$attempt) {
                return response()->json(['message' => 'Assessment tidak ditemukan.'], 404);
            }
            $activeSession = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->where('status', 'in_progress')->first();
            if (!$activeSession) {
                $pendingSession = DB::table('assessment_sessions')
                    ->where('assessment_attempt_id', $attempt->id)
                    ->where('status', 'pending')
                    ->orderBy('session_order')
                    ->lockForUpdate()
                    ->first();

                if ($pendingSession && !$this->sessionIsReady($pendingSession)) {
                    return response()->json([
                        'status' => 'blocked',
                        'can_start' => false,
                        'message' => $this->sessionStartBlockReason($pendingSession),
                        'session' => $this->sessionPayload($pendingSession),
                    ]);
                }

                $this->startNextSession($attempt->id);
            }

            return $this->jsonState($attempt);
        });
    }

    public function preview(Request $request)
    {
        $recruitment = $this->recruitment($request->token);
        if (!$recruitment) {
            return $this->expiredLinkResponse();
        }

        $attempt = $this->ensureAttempt($recruitment);
        return response()->json(['questions' => $this->previewQuestions($attempt->id, 3), 'duration_seconds' => 300]);
    }

    public function state(Request $request)
    {
        $attempt = $this->attemptByToken($request->token);
        if (!$attempt) {
            return response()->json(['message' => 'Assessment tidak ditemukan.'], 404);
        }
        return $this->jsonState($attempt);
    }

    public function answer(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $attempt = $this->attemptByToken($request->token, true);
            if (!$attempt) {
                return response()->json(['message' => 'Assessment tidak ditemukan.'], 404);
            }
            $state = $this->stateFor($attempt);
            if (($state['status'] ?? null) === 'expired') {
                return response()->json($state, 403);
            }
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
            return $this->jsonState($attempt);
        });
    }

    public function proctoringEvent(Request $request)
    {
        $allowed = ['camera_granted', 'camera_denied', 'camera_stopped', 'tab_hidden', 'tab_visible', 'window_blur', 'window_focus', 'data_processing_consent'];
        if (!in_array($request->event, $allowed, true)) return response()->json(['message' => 'Event tidak valid.'], 422);
        $attempt = $this->attemptByToken($request->token);
        if (!$attempt) {
            return response()->json(['message' => 'Assessment tidak ditemukan.'], 404);
        }
        $meta = json_decode($attempt->proctoring_meta ?: '[]', true) ?: [];
        $meta[] = ['event' => $request->event, 'at' => Carbon::now()->toDateTimeString()];
        DB::table('assessment_attempts')->where('id', $attempt->id)->update(['proctoring_meta' => json_encode($meta), 'updated_at' => Carbon::now()]);
        return response()->json(['status' => true]);
    }

    public function proctoringStatus(Request $request)
    {
        $attempt = $this->attemptByToken($request->token);
        if (!$attempt) {
            return response()->json(['message' => 'Assessment tidak ditemukan.'], 404);
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
                    return $this->sessionDefinitionFromSession($session);
                })->all();
                if (!$hasStartedSession && $sessionDefinitions !== $expectedSessions) {
                    DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->delete();
                    $this->createSessions($attempt->id, Carbon::now(), $recruitment);
                } else {
                    $pendingSessions = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->where('status', 'pending')->get();
                    $userConfig = $this->userAssessmentConfig($recruitment);

                    $hasDeletedInformasiPendukung = false;
                    foreach ($pendingSessions as $session) {
                        if (strtoupper(trim((string) $session->category_name)) === 'INFORMASI PENDUKUNG') {
                            DB::table('assessment_sessions')->where('id', $session->id)->delete();
                            $hasDeletedInformasiPendukung = true;
                            continue;
                        }

                        if (($session->category_name ?? '') === 'Assessment User') {
                            if (!$userConfig) {
                                DB::table('assessment_sessions')->where('id', $session->id)->delete();
                                continue;
                            }

                            $needsUserSessionRefresh = $this->sessionDefinitionFromSession($session) !== $this->userSessionDefinition($userConfig)
                                || !$this->sessionIsReady($session);

                            if ($needsUserSessionRefresh) {
                                $items = $this->userSessionQuestions($userConfig);

                                DB::table('assessment_sessions')->where('id', $session->id)->update([
                                    'question_count' => $userConfig->question_count,
                                    'duration_minutes' => $userConfig->duration_minutes,
                                    'questions_json' => json_encode($items),
                                    'updated_at' => Carbon::now(),
                                ]);
                            }

                            continue;
                        }

                        $category = DB::table('question_categories')->where('id', $session->question_category_id)->first();
                        $needsCategorySessionRefresh = $category && (
                            $this->sessionDefinitionFromSession($session) !== $this->sessionDefinitionFromCategory($category)
                            || !$this->sessionIsReady($session)
                        );

                        if ($needsCategorySessionRefresh) {
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

                    if ($hasDeletedInformasiPendukung) {
                        $remainingSessions = DB::table('assessment_sessions')
                            ->where('assessment_attempt_id', $attempt->id)
                            ->orderBy('session_order')
                            ->get();

                        foreach ($remainingSessions as $index => $s) {
                            if ((int) $s->session_order !== ($index + 1)) {
                                DB::table('assessment_sessions')->where('id', $s->id)->update([
                                    'session_order' => $index + 1,
                                    'updated_at' => Carbon::now(),
                                ]);
                            }
                        }
                    }

                    if ($userConfig && !$hasStartedSession) {
                        $hasUserSession = DB::table('assessment_sessions')
                            ->where('assessment_attempt_id', $attempt->id)
                            ->where('category_name', 'Assessment User')
                            ->exists();

                        if (!$hasUserSession) {
                            $items = $this->userSessionQuestions($userConfig);

                            $nextOrder = (int) DB::table('assessment_sessions')
                                ->where('assessment_attempt_id', $attempt->id)
                                ->max('session_order');

                            DB::table('assessment_sessions')->insert([
                                'assessment_attempt_id' => $attempt->id,
                                'question_category_id' => null,
                                'session_order' => $nextOrder + 1,
                                'category_name' => 'Assessment User',
                                'question_count' => $userConfig->question_count,
                                'duration_minutes' => $userConfig->duration_minutes,
                                'questions_json' => json_encode($items),
                                'answers_json' => json_encode(new \stdClass()),
                                'result_json' => null,
                                'status' => 'pending',
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
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
            DB::table('assessment_sessions')->insert(['assessment_attempt_id' => $attemptId, 'question_category_id' => $category->id, 'session_order' => $index + 1, 'category_name' => $category->name, 'question_count' => $category->question_count, 'duration_minutes' => $category->duration_minutes, 'questions_json' => json_encode($items), 'answers_json' => json_encode(new \stdClass()), 'result_json' => null, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        }

        $userConfig = $this->userAssessmentConfig($recruitment);
        if (!$userConfig) {
            return;
        }

        $items = $this->userSessionQuestions($userConfig);

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
            return $this->sessionDefinitionFromCategory($category);
        })->all();

        $userConfig = $this->userAssessmentConfig($recruitment);
        if ($userConfig) {
            $definitions[] = $this->userSessionDefinition($userConfig);
        }

        return $definitions;
    }

    private function userSessionDefinition($userConfig)
    {
        return [
            'Assessment User',
            (int) $userConfig->question_count,
            (int) $userConfig->duration_minutes,
            (bool) $userConfig->has_time_limit,
            (int) ($userConfig->question_category_id ?? 0),
        ];
    }

    private function userAssessmentConfig($recruitment)
    {
        $personnelRequest = DB::table('personnel_requests')->where('id', $recruitment->personnel_request_id)->first();
        if (!$personnelRequest || (int) ($personnelRequest->use_user_assessment ?? 0) !== 1 || empty($personnelRequest->created_by)) {
            return null;
        }

        $questionCategoryId = !empty($personnelRequest->assesment_question_category)
            ? (int) $personnelRequest->assesment_question_category
            : null;

        $service = app(UserAssessmentCategoryService::class);
        $ownerCategory = $service->findOwnerCategory((string) $personnelRequest->created_by);
        $categoryConfig = $service->toConfigObject($ownerCategory);

        if ($categoryConfig && (int) $categoryConfig->question_count >= 1) {
            return (object) [
                'owner_karyawan' => $personnelRequest->created_by,
                'question_count' => (int) $categoryConfig->question_count,
                'duration_minutes' => (int) $categoryConfig->duration_minutes,
                'has_time_limit' => (bool) $categoryConfig->has_time_limit,
                'question_category_id' => $questionCategoryId ?: ($ownerCategory ? (int) $ownerCategory->id : null),
            ];
        }

        $questionCount = (int) ($personnelRequest->user_assessment_question_count ?? 0);
        if ($questionCount < 1) {
            throw new \RuntimeException('Konfigurasi jumlah soal test user pada kategori bank soal belum diisi.');
        }

        $hasTimeLimit = (int) ($personnelRequest->user_assessment_has_time_limit ?? 0) === 1;
        $durationMinutes = $hasTimeLimit ? (int) ($personnelRequest->user_assessment_duration_minutes ?? 0) : 0;

        if ($hasTimeLimit && $durationMinutes < 1) {
            throw new \RuntimeException('Konfigurasi durasi test user pada kategori bank soal belum diisi.');
        }

        return (object) [
            'owner_karyawan' => $personnelRequest->created_by,
            'question_count' => $questionCount,
            'duration_minutes' => $durationMinutes,
            'has_time_limit' => $hasTimeLimit,
            'question_category_id' => $questionCategoryId,
        ];
    }

    private function managerHierarchyNamesForKaryawan(string $karyawanName): array
    {
        $employee = DB::table('master_karyawan')
            ->where('nama_lengkap', $karyawanName)
            ->where('is_active', 1)
            ->first(['id']);

        if (!$employee) {
            return array_values(array_filter([(string) $karyawanName]));
        }

        return GetBawahan::where('id', (int) $employee->id)
            ->get()
            ->pluck('nama_lengkap')
            ->filter()
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();
    }

    private function accessibleManagerCategoryIdsForHierarchy(array $hierarchyNames, string $rootKaryawan): array
    {
        if (empty($hierarchyNames)) {
            return [];
        }

        return DB::table('question_categories')
            ->where('category_scope', 'manager')
            ->where('is_active', 1)
            ->where(function ($query) use ($hierarchyNames, $rootKaryawan) {
                $query->whereIn('owner_karyawan', $hierarchyNames)
                    ->orWhereIn('assigned_manager', $hierarchyNames)
                    ->orWhere('assigned_manager', $rootKaryawan);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function userSessionQuestions($config)
    {
        $hierarchyNames = $this->managerHierarchyNamesForKaryawan((string) $config->owner_karyawan);
        $rootKaryawan = (string) $config->owner_karyawan;

        $query = DB::table('questions')
            ->where('question_scope', 'manager')
            ->where('status', 'active')
            ->where('is_active', 1)
            ->where('question_type', 'single_choice');

        if (!empty($config->question_category_id)) {
            $query->where('question_category_id', (int) $config->question_category_id);
        } else {
            $accessibleCategoryIds = $this->accessibleManagerCategoryIdsForHierarchy($hierarchyNames, $rootKaryawan);

            $query->where(function ($builder) use ($hierarchyNames, $accessibleCategoryIds) {
                $builder->whereIn('owner_karyawan', $hierarchyNames);

                if (!empty($accessibleCategoryIds)) {
                    $builder->orWhereIn('question_category_id', $accessibleCategoryIds);
                }
            });
        }

        return $query->inRandomOrder()
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
        $excludedCategoryIds = DB::table('question_categories')
            ->whereRaw('UPPER(name) = ?', ['INFORMASI PENDUKUNG'])
            ->pluck('id')
            ->all();
        $query = DB::table('questions')->where('is_active', 1)->whereIn('question_type', ['single_choice', 'multiple_choice']);
        if (!empty($selectedIds)) {
            $query->whereNotIn('id', $selectedIds);
        }
        if (!empty($excludedCategoryIds)) {
            $query->whereNotIn('question_category_id', $excludedCategoryIds);
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
            ->where(function ($query) {
                $query->where('category_scope', 'hr')->orWhereNull('category_scope');
            })
            ->whereRaw('UPPER(name) != ?', ['INFORMASI PENDUKUNG'])
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
                $options = $scale ? ScaleScoringService::buildScaleOptions($scale) : [];
                $values = collect($options)->pluck('value');
                $scaleMin = $values->isNotEmpty() ? (float) $values->min() : 0;
                $scaleMax = $values->isNotEmpty() ? (float) $values->max() : 0;
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

            if ($question->question_type === 'scale') {
                $payload['scale_type_id'] = $question->scale_type_id;
                $payload['scale_min'] = $scaleMin ?? 0;
                $payload['scale_max'] = $scaleMax ?? 0;
            }

            return $payload;
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
        $row = $this->findRecruitmentByToken($token, $lock);
        if (!$row || Carbon::now()->greaterThanOrEqualTo(Carbon::parse($row->created_at)->addDays(2))) {
            return null;
        }
        return $row;
    }

    private function expiredLinkResponse($message = 'Link assessment tidak valid atau sudah kedaluwarsa.')
    {
        return response()->json([
            'result' => 'expired',
            'status' => 'expired',
            'message' => $message,
        ], 403);
    }

    private function jsonState($attempt)
    {
        $state = $this->stateFor($attempt);
        if (($state['status'] ?? null) === 'expired') {
            return response()->json($state, 403);
        }

        return response()->json($state);
    }

    private function attemptByToken($token, $lock = false)
    {
        $query = DB::table('assessment_attempts')->where('token', $this->normalizeAssessmentToken($token));
        if ($lock) {
            $query->lockForUpdate();
        }
        $attempt = $query->first();
        if ($attempt) {
            return $attempt;
        }

        $recruitment = $this->findRecruitmentByToken($token, $lock);
        if (!$recruitment) {
            return null;
        }

        $fallbackQuery = DB::table('assessment_attempts')->where('recruitment_id', $recruitment->id);
        if ($lock) {
            $fallbackQuery->lockForUpdate();
        }

        return $fallbackQuery->first();
    }

    private function findRecruitmentByToken($token, $lock = false)
    {
        foreach ($this->assessmentTokenCandidates($token) as $candidate) {
            $query = DB::table('new_recruitment')->where('token', $candidate);
            if ($lock) {
                $query->lockForUpdate();
            }
            $row = $query->first();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private function assessmentTokenCandidates($token)
    {
        $token = trim((string) $token);
        $candidates = [$token];

        $decoded = $token;
        while (($next = rawurldecode($decoded)) !== $decoded) {
            $decoded = trim($next);
            $candidates[] = $decoded;
        }

        $decoded = urldecode($token);
        if ($decoded !== $token) {
            $candidates[] = trim($decoded);
        }

        if (strpos($token, ' ') !== false) {
            $candidates[] = str_replace(' ', '+', $token);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeAssessmentToken($token)
    {
        $candidates = $this->assessmentTokenCandidates($token);

        return $candidates[0] ?? '';
    }

    private function stateFor($attempt)
    {
        if (!$attempt || Carbon::now()->greaterThanOrEqualTo(Carbon::parse($attempt->token_expires_at))) { if ($attempt) DB::table('assessment_attempts')->where('id', $attempt->id)->update(['status' => 'expired']); return ['status' => 'expired', 'message' => 'Waktu akses assessment sudah habis.']; }
        $session = DB::table('assessment_sessions')->where('assessment_attempt_id', $attempt->id)->whereIn('status', ['pending', 'in_progress'])->orderBy('session_order')->first();
        if (!$session) {
            if (($attempt->status ?? null) !== 'completed') {
                $completedAt = Carbon::now();
                DB::table('assessment_attempts')->where('id', $attempt->id)->update([
                    'status' => 'completed',
                    'completed_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);
                (new RecruitmentStatusService())->update($attempt->recruitment_id, 'screening', $completedAt);
                $recruitment = DB::table('new_recruitment')->where('id', $attempt->recruitment_id)->first();
                if ($recruitment) {
                    app(AtsNotificationService::class)->assessmentCompleted($recruitment);
                }
            }

            $recruitment = DB::table('new_recruitment')->where('id', $attempt->recruitment_id)->first();
            if ($recruitment && !$this->hasSuccessfulAiMatching($recruitment)) {
                $this->processAiMatching($attempt->id, $attempt->recruitment_id);
            }

            return ['status' => 'completed', 'message' => 'Assessment selesai.'];
        }
        if ($session->status === 'pending') {
            $sessionPayload = $this->sessionPayload($session);

            return [
                'status' => 'waiting',
                'can_start' => $this->sessionIsReady($session),
                'message' => $this->sessionIsReady($session)
                    ? 'Sesi berikutnya siap dimulai saat Anda sudah siap.'
                    : $this->sessionStartBlockReason($session),
                'session' => $sessionPayload,
            ];
        }
        $questions = json_decode($session->questions_json ?: '[]', true) ?: []; $answers = json_decode($session->answers_json ?: '{}', true) ?: [];
        if ($session->expires_at && Carbon::now()->greaterThanOrEqualTo(Carbon::parse($session->expires_at))) { foreach ($questions as $question) if (!array_key_exists($question['id'], $answers)) $answers[$question['id']] = null; $this->finishSession($session, $answers, 'expired'); return $this->stateFor($attempt); }
        $question = collect($questions)->first(function ($item) use ($answers) { return !array_key_exists($item['id'], $answers); });
        if (!$question) { $this->finishSession($session, $answers, 'completed'); return $this->stateFor($attempt); }
        unset($question['answer_key'], $question['answer_map']); foreach ($question['options'] as &$option) unset($option['is_correct']);
        return ['status' => 'in_progress', 'session' => ['id' => $session->id, 'name' => $session->category_name, 'order' => $session->session_order, 'duration_minutes' => $session->duration_minutes, 'has_time_limit' => $this->sessionHasTimeLimit($session), 'expires_at' => $session->expires_at], 'question' => array_merge($question, ['total' => $session->question_count])];
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

        $scaleQuestions = collect($questions)->filter(function ($question) {
            return ($question['type'] ?? '') === 'scale' || ($question['scoring_type'] ?? '') === 'scale_average';
        });
        $choiceQuestions = collect($questions)->reject(function ($question) {
            return ($question['type'] ?? '') === 'scale' || ($question['scoring_type'] ?? '') === 'scale_average';
        });

        if ($scaleQuestions->isNotEmpty() && $choiceQuestions->isEmpty()) {
            return ScaleScoringService::scoreQuestions($questions, $answers);
        }

        $answered = 0;
        $correct = 0;
        foreach ($choiceQuestions as $question) {
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

        $choiceTotal = $choiceQuestions->count();
        $choiceScore = $choiceTotal ? round(($correct / $choiceTotal) * 100, 2) : null;
        $scaleResult = $scaleQuestions->isNotEmpty()
            ? ScaleScoringService::scoreQuestions($scaleQuestions->values()->all(), $answers)
            : null;

        if ($scaleResult && $choiceTotal > 0) {
            $combinedTotal = $choiceTotal + ($scaleResult['total_questions'] ?? 0);
            $combinedScore = $combinedTotal > 0
                ? round((($choiceScore * $choiceTotal) + (($scaleResult['score'] ?? 0) * ($scaleResult['total_questions'] ?? 0))) / $combinedTotal, 2)
                : 0;

            return [
                'engine' => 'mixed',
                'answered' => $answered + ($scaleResult['answered'] ?? 0),
                'total_questions' => count($questions),
                'correct_answers' => $correct,
                'choice_score' => $choiceScore,
                'scale_score' => $scaleResult['score'] ?? 0,
                'scale_details' => $scaleResult['details'] ?? [],
                'score' => min(100, max(0, $combinedScore)),
            ];
        }

        if ($scaleResult) {
            return $scaleResult;
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
        if (!$session || !$this->sessionIsReady($session)) {
            return null;
        }

        $start = Carbon::now();
        DB::table('assessment_sessions')->where('id', $session->id)->update([
            'status' => 'in_progress',
            'started_at' => $start,
            'expires_at' => $this->sessionHasTimeLimit($session)
                ? $start->copy()->addMinutes(max(1, (int) $session->duration_minutes))
                : null,
            'updated_at' => $start,
        ]);

        return $session->id;
    }

    private function sessionQuestionItems($session)
    {
        return json_decode($session->questions_json ?? '[]', true) ?: [];
    }

    private function sessionIsReady($session)
    {
        if (!$session) {
            return false;
        }

        $required = (int) ($session->question_count ?? 0);
        if ($required < 1) {
            return false;
        }

        return count($this->sessionQuestionItems($session)) >= $required;
    }

    private function sessionStartBlockReason($session)
    {
        if (!$session) {
            return 'Sesi assessment belum tersedia.';
        }

        $required = (int) ($session->question_count ?? 0);
        $available = count($this->sessionQuestionItems($session));
        $sessionName = $session->category_name ?? 'Assessment';

        if ($required < 1) {
            return 'Konfigurasi jumlah soal sesi ' . $sessionName . ' belum diatur.';
        }

        if ($available === 0) {
            return 'Soal untuk sesi ' . $sessionName . ' belum tersedia. Sesi belum dapat dimulai.';
        }

        if ($available < $required) {
            return 'Soal untuk sesi ' . $sessionName . ' belum mencukupi (' . $available . ' dari ' . $required . '). Sesi belum dapat dimulai.';
        }

        return null;
    }

    private function sessionPayload($session)
    {
        $availableCount = count($this->sessionQuestionItems($session));

        return [
            'id' => $session->id,
            'name' => $session->category_name,
            'order' => (int) $session->session_order,
            'duration_minutes' => (int) $session->duration_minutes,
            'has_time_limit' => $this->sessionHasTimeLimit($session),
            'question_count' => (int) $session->question_count,
            'available_question_count' => $availableCount,
            'can_start' => $this->sessionIsReady($session),
            'block_reason' => $this->sessionStartBlockReason($session),
        ];
    }

    private function sessionDefinitionFromCategory($category)
    {
        return [
            $category->name,
            (int) $category->question_count,
            (int) $category->duration_minutes,
            $this->categoryHasTimeLimit($category),
        ];
    }

    private function sessionDefinitionFromSession($session)
    {
        return [
            $session->category_name,
            (int) $session->question_count,
            (int) $session->duration_minutes,
            $this->sessionHasTimeLimit($session),
        ];
    }

    private function categoryHasTimeLimit($category)
    {
        if (isset($category->has_time_limit)) {
            return (bool) $category->has_time_limit;
        }

        return (int) ($category->duration_minutes ?? 0) > 0;
    }

    private function sessionHasTimeLimit($session)
    {
        $category = $session->question_category_id
            ? DB::table('question_categories')->where('id', $session->question_category_id)->first()
            : null;

        if ($category) {
            return $this->categoryHasTimeLimit($category);
        }

        if (($session->category_name ?? '') === 'Assessment User') {
            return (int) ($session->duration_minutes ?? 0) > 0;
        }

        return (int) ($session->duration_minutes ?? 0) > 0;
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

            if ($this->hasSuccessfulAiMatching($recruitment)) {
                $this->logAiMatching('info', 'AI matching skipped (already processed)', [
                    'recruitment_id' => $recruitmentId,
                    'attempt_id' => $attemptId,
                ]);

                if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_data')
                    && !empty($recruitment->ai_matching_data)) {
                    return json_decode($recruitment->ai_matching_data, true);
                }

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

            // Format Skill & Keahlian Kandidat (kolom skill: [{"rate": 8, "keahlian": "JavaScript"}, ...])
            $candidateSkillsStr = $this->candidateSkillsForAi($recruitment);

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
                "Analisis kecocokan kandidat terhadap posisi yang dilamar.\n\nKandidat:\n- Pendidikan: %s\n- Pengalaman: %s\n- Skill: %s\n- Hasil assessment: %s\n\nPosisi: %s\nKebutuhan pendidikan: %s\nKebutuhan pengalaman: %s\nKebutuhan skill: %s\n\nBerikan skor kecocokan 0-100 (integer) dan alasan singkat dalam Bahasa Indonesia.",
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

            // Construct payload for Ollama structured JSON response
            $aiPayload = [
                'model' => 'intilab-ats',
                'prompt' => $promptText,
                'stream' => false,
                'format' => [
                    'type' => 'object',
                    'properties' => [
                        'score' => [
                            'type' => 'integer',
                        ],
                        'reason' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => [
                        'score',
                        'reason',
                    ],
                    'additionalProperties' => false,
                ],
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
                    'skills' => $this->candidateSkillsList($recruitment),
                ],
                'assessment_results' => $structuredSessions,
                'ai_payload' => $aiPayload,
            ];

            // Log AI request (prompt once, metadata only in payload summary)
            $this->logAiMatching('info', 'AI matching request', [
                'recruitment_id' => $recruitmentId,
                'attempt_id' => $attemptId,
                'kandidat' => $recruitment->nama_lengkap ?? '',
                'model' => $aiPayload['model'] ?? null,
                'prompt_length' => strlen($promptText),
                'prompt' => $promptText,
            ]);

            // Send to Ollama AI Server via HTTP POST cURL
            $aiResult = $this->sendToOllamaAi($aiPayload);

            // Log AI response (strip verbose token context from Ollama)
            $this->logAiMatching('info', 'AI matching response', [
                'recruitment_id' => $recruitmentId,
                'attempt_id' => $attemptId,
                'kandidat' => $recruitment->nama_lengkap ?? '',
                'ai_response' => $this->summarizeAiMatchingResponse($aiResult),
            ]);

            if ($aiResult && !empty($aiResult['response'])) {
                $parsedResponse = json_decode($aiResult['response'], true);
                $matchingScore = null;
                $matchingReason = null;
                $responseStr = $aiResult['response'];

                if (is_array($parsedResponse)) {
                    if (isset($parsedResponse['score'])) {
                        $matchingScore = max(0, min(100, (int) $parsedResponse['score']));
                    }
                    if (isset($parsedResponse['reason'])) {
                        $matchingReason = trim((string) $parsedResponse['reason']);
                    }
                    if ($matchingScore !== null || $matchingReason !== null) {
                        $responseStr = json_encode([
                            'score' => $matchingScore,
                            'reason' => $matchingReason ?? '',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                } elseif (preg_match('/(\d{1,3})\s*%?/', $aiResult['response'], $matches)) {
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
                if ($matchingReason !== null && $matchingReason !== ''
                    && \Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'ai_matching_reason')) {
                    $updateData['ai_matching_reason'] = $matchingReason;
                }
                if ($matchingScore !== null) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'matching_score')) {
                        $updateData['matching_score'] = $matchingScore;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('new_recruitment', 'nilai_kecocokan')) {
                        $updateData['nilai_kecocokan'] = $matchingScore;
                    }
                }

                DB::table('new_recruitment')->where('id', $recruitmentId)->update($updateData);
                $structuredData['ai_response'] = $aiResult;
            }

            return $structuredData;
        } catch (\Throwable $e) {
            $this->logAiMatching('error', 'processAiMatching error', [
                'recruitment_id' => $recruitmentId,
                'attempt_id' => $attemptId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function candidateSkillsList($recruitment): array
    {
        $skills = [];

        if (!empty($recruitment->skill)) {
            foreach (json_decode($recruitment->skill, true) ?: [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $keahlian = trim((string) ($item['keahlian'] ?? $item['skill'] ?? ''));
                if ($keahlian === '') {
                    continue;
                }
                $skills[] = [
                    'keahlian' => $keahlian,
                    'rate' => isset($item['rate']) && $item['rate'] !== '' ? (int) $item['rate'] : null,
                ];
            }
        } elseif (!empty($recruitment->keahlian)) {
            foreach (json_decode($recruitment->keahlian, true) ?: [] as $item) {
                if (!is_array($item) || empty($item['keahlianSkill'])) {
                    continue;
                }
                $skills[] = [
                    'keahlian' => trim((string) $item['keahlianSkill']),
                    'rate' => null,
                ];
            }
        }

        return $skills;
    }

    private function candidateSkillsForAi($recruitment): string
    {
        $skillsList = collect($this->candidateSkillsList($recruitment))
            ->map(function ($skill) {
                if ($skill['rate'] !== null) {
                    return sprintf('%s (tingkat %d/10)', $skill['keahlian'], $skill['rate']);
                }

                return $skill['keahlian'];
            })
            ->filter()
            ->values()
            ->all();

        return !empty($skillsList)
            ? implode(', ', $skillsList)
            : 'Skill tidak spesifik';
    }

    private function logAiMatching(string $level, string $message, array $context = []): void
    {
        Log::channel('ats_ai_matching')->{$level}($message, $context);
    }

    private function summarizeAiMatchingResponse(?array $aiResult): ?array
    {
        if (!$aiResult) {
            return null;
        }

        $parsedResponse = null;
        if (!empty($aiResult['response'])) {
            $decoded = json_decode($aiResult['response'], true);
            $parsedResponse = is_array($decoded) ? $decoded : $aiResult['response'];
        }

        return [
            'model' => $aiResult['model'] ?? null,
            'created_at' => $aiResult['created_at'] ?? null,
            'done' => $aiResult['done'] ?? null,
            'done_reason' => $aiResult['done_reason'] ?? null,
            'response' => $parsedResponse,
            'prompt_eval_count' => $aiResult['prompt_eval_count'] ?? null,
            'eval_count' => $aiResult['eval_count'] ?? null,
            'total_duration' => $aiResult['total_duration'] ?? null,
        ];
    }

    private function summarizeAiMatchingPayload(array $payload): array
    {
        $prompt = (string) ($payload['prompt'] ?? '');

        return [
            'model' => $payload['model'] ?? null,
            'stream' => $payload['stream'] ?? null,
            'format' => $payload['format'] ?? null,
            'prompt_length' => strlen($prompt),
        ];
    }

    private function hasSuccessfulAiMatching($recruitment): bool
    {
        if (!$recruitment) {
            return false;
        }

        if (!empty($recruitment->ai_matching_response)) {
            $parsed = json_decode($recruitment->ai_matching_response, true);
            if (is_array($parsed) && array_key_exists('score', $parsed)) {
                return true;
            }
        }

        $score = $recruitment->matching_score ?? $recruitment->nilai_kecocokan ?? null;
        if ($score === null || $score === '') {
            return false;
        }

        return (float) $score > 0;
    }

    /**
     * Send HTTP POST request to Ollama AI Server (ATS_AI_GENERATE_URL).
     */
    private function sendToOllamaAi(array $payload)
    {
        $endpoint = rtrim((string) env('ATS_AI_GENERATE_URL', 'http://10.88.209.240:11434/api/generate'), '/');

        try {
            $ch = curl_init($endpoint);
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
                $this->logAiMatching('error', 'sendToOllamaAi cURL error', [
                    'endpoint' => $endpoint,
                    'error' => $curlError,
                    'payload' => $this->summarizeAiMatchingPayload($payload),
                ]);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }

            $this->logAiMatching('warning', 'sendToOllamaAi HTTP error', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => $response,
                'payload' => $this->summarizeAiMatchingPayload($payload),
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->logAiMatching('error', 'sendToOllamaAi exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'payload' => $this->summarizeAiMatchingPayload($payload),
            ]);
            return null;
        }
    }
}
