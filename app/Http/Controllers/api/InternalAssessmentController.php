<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ScaleScoringService;

class InternalAssessmentController extends Controller
{
    public function entry(Request $request)
    {
        $assessment = $this->assessmentByToken($request->token);

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->nama_assesment,
                'batch' => $assessment->batch,
            ],
            'status' => 'email_required',
        ]);
    }

    public function register(Request $request)
    {
        $email = strtolower(trim((string) $request->email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Format email tidak valid.'], 422);
        }

        $assessment = $this->assessmentByToken($request->token);
        $employee = DB::table('master_karyawan')->whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$employee) {
            return response()->json(['message' => 'Email tidak terdaftar sebagai karyawan.'], 422);
        }

        $result = DB::transaction(function () use ($assessment, $employee, $email) {
            DB::table('assessment_internal')->where('id', $assessment->id)->lockForUpdate()->first();
            $attempt = DB::table('assessment_internal_attempts')
                ->where('assessment_internal_id', $assessment->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if ($attempt && $attempt->status === 'completed') {
                return ['completed' => true];
            }

            $accessToken = bin2hex(random_bytes(32));
            $now = Carbon::now();
            if (!$attempt) {
                $attemptId = DB::table('assessment_internal_attempts')->insertGetId([
                    'assessment_internal_id' => $assessment->id,
                    'email' => $email,
                    'participant_name' => $employee->nama_lengkap ?? null,
                    'access_token_hash' => hash('sha256', $accessToken),
                    'status' => 'in_progress',
                    'started_at' => $now,
                    'last_activity_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $attemptId = $attempt->id;
                DB::table('assessment_internal_attempts')->where('id', $attemptId)->update([
                    'access_token_hash' => hash('sha256', $accessToken),
                    'last_activity_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->ensureSessions($assessment, $attemptId);
            $attempt = DB::table('assessment_internal_attempts')->where('id', $attemptId)->first();
            return [
                'completed' => false,
                'attempt_id' => $attemptId,
                'access_token' => $accessToken,
                'send_registration_email' => !$attempt->registration_email_sent_at,
            ];
        });

        if ($result['completed']) {
            return response()->json([
                'status' => 'completed',
                'message' => 'Email ini sudah menyelesaikan assessment dan tidak dapat mengulang.',
            ], 409);
        }

        if ($result['send_registration_email']) {
            $assessmentUrl = trim((string) $request->assessment_url);
            if ($assessmentUrl === '') {
                $assessmentUrl = rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/')
                    . '/private/assessment/' . rawurlencode((string) $request->token);
            }
            $this->sendRegistrationEmail($employee, $assessment, $assessmentUrl);
            DB::table('assessment_internal_attempts')->where('id', $result['attempt_id'])->update([
                'registration_email_sent_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'user' => ['email' => $email, 'name' => $employee->nama_lengkap ?? null],
            'access_token' => $result['access_token'],
            'state' => $this->statePayload($result['attempt_id']),
        ]);
    }

    public function state(Request $request)
    {
        $attempt = $this->authorizedAttempt($request);
        $assessment = DB::table('assessment_internal')->where('id', $attempt->assessment_internal_id)->first();
        $this->ensureSessions($assessment, $attempt->id);

        return response()->json($this->statePayload($attempt->id));
    }

    public function start(Request $request)
    {
        $attempt = $this->authorizedAttempt($request);

        if (!$attempt->consent_at && !(bool) $request->consent) {
            return response()->json(['message' => 'Persetujuan monitoring assessment wajib diberikan.'], 422);
        }
        $isFirstConfirmedStart = !$attempt->consent_at;

        DB::transaction(function () use ($attempt, $request, $isFirstConfirmedStart) {
            if (!$attempt->consent_at) {
                DB::table('assessment_internal_attempts')->where('id', $attempt->id)->update([
                    'consent_at' => Carbon::now(),
                    'last_activity_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $this->storeEvent($attempt->id, null, 'data_processing_consent');
            }

            $active = DB::table('assessment_internal_sessions')
                ->where('assessment_internal_attempt_id', $attempt->id)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();
            if ($active) {
                if ($isFirstConfirmedStart) {
                    DB::table('assessment_internal_sessions')->where('id', $active->id)->update([
                        'started_at' => Carbon::now(),
                        'expires_at' => $active->duration_minutes
                            ? Carbon::now()->addMinutes((int) $active->duration_minutes)
                            : null,
                        'updated_at' => Carbon::now(),
                    ]);
                }
                return;
            }

            $pending = DB::table('assessment_internal_sessions')
                ->where('assessment_internal_attempt_id', $attempt->id)
                ->where('status', 'pending')
                ->orderBy('session_order')
                ->lockForUpdate()
                ->first();
            if (!$pending) {
                return;
            }

            DB::table('assessment_internal_sessions')->where('id', $pending->id)->update([
                'status' => 'in_progress',
                'started_at' => Carbon::now(),
                'expires_at' => $pending->duration_minutes
                    ? Carbon::now()->addMinutes((int) $pending->duration_minutes)
                    : null,
                'updated_at' => Carbon::now(),
            ]);
        });

        return response()->json($this->statePayload($attempt->id));
    }

    public function event(Request $request)
    {
        $attempt = $this->authorizedAttempt($request);
        $allowed = ['tab_hidden', 'tab_visible', 'window_blur', 'window_focus'];
        if (!in_array($request->event, $allowed, true)) {
            return response()->json(['message' => 'Event tidak valid.'], 422);
        }

        $sessionId = DB::table('assessment_internal_sessions')
            ->where('assessment_internal_attempt_id', $attempt->id)
            ->where('status', 'in_progress')
            ->value('id');
        if (!$sessionId) {
            return response()->json(['status' => true, 'recorded' => false]);
        }

        $recorded = !in_array($request->event, ['tab_visible', 'window_focus'], true);
        if ($recorded) {
            $this->storeEvent($attempt->id, $sessionId, $request->event, [
                'user_agent' => substr((string) ($request->user_agent ?: $request->header('User-Agent')), 0, 500),
                'ip_address' => $request->ip_address ?: $request->ip(),
            ]);
        }
        return response()->json([
            'status' => true,
            'recorded' => $recorded,
            'proctoring' => $this->proctoringPayload($attempt->id),
        ]);
    }

    public function answer(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $authorizedAttempt = $this->authorizedAttempt($request);
            $attempt = DB::table('assessment_internal_attempts')
                ->where('id', $authorizedAttempt->id)
                ->lockForUpdate()
                ->first();
            $state = $this->statePayload($attempt->id);
            if (($state['status'] ?? null) !== 'in_progress' || (string) $state['question']['id'] !== (string) $request->question_id) {
                return response()->json($state, 409);
            }

            $session = DB::table('assessment_internal_sessions')
                ->where('id', $state['session']['id'])
                ->lockForUpdate()
                ->first();
            $answers = json_decode($session->answers_json ?: '{}', true) ?: [];
            $question = collect(json_decode($session->questions_json ?: '[]', true) ?: [])
                ->firstWhere('id', (string) $request->question_id);

            if ($question && $question['type'] === 'disc') {
                $most = $request->answer['P'] ?? null;
                $least = $request->answer['K'] ?? null;
                if ($most === null || $least === null || (string) $most === (string) $least) {
                    return response()->json(['message' => 'Pilih pernyataan yang paling dan paling tidak menggambarkan diri Anda.'], 422);
                }
                if (!array_key_exists($request->question_id, $answers)) {
                    $answers[$request->question_id] = ['P' => (string) $most, 'K' => (string) $least];
                }
            } elseif (!array_key_exists($request->question_id, $answers)) {
                $answers[$request->question_id] = is_array($request->answer)
                    ? array_values($request->answer)
                    : [$request->answer];
            }

            DB::table('assessment_internal_sessions')->where('id', $session->id)->update([
                'answers_json' => json_encode($answers),
                'updated_at' => Carbon::now(),
            ]);
            DB::table('assessment_internal_attempts')->where('id', $attempt->id)->update([
                'last_activity_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return response()->json($this->statePayload($attempt->id));
        });
    }

    public function updateProfile(Request $request)
    {
        $attempt = $this->authorizedAttempt($request);
        $assessment = DB::table('assessment_internal')->where('id', $attempt->assessment_internal_id)->first();
        if (!$assessment || !(bool) ($assessment->is_completed_profile ?? false)) {
            return response()->json(['message' => 'Sesi kelengkapan profil tidak tersedia.'], 404);
        }
        if (DB::table('assessment_internal_sessions')
            ->where('assessment_internal_attempt_id', $attempt->id)
            ->whereNotIn('status', ['completed', 'expired'])
            ->exists()) {
            return response()->json(['message' => 'Selesaikan seluruh sesi assessment terlebih dahulu.'], 409);
        }

        $employee = DB::table('master_karyawan')->whereRaw('LOWER(email) = ?', [strtolower($attempt->email)])->first();
        if (!$employee) {
            return response()->json(['message' => 'Data karyawan tidak ditemukan.'], 404);
        }

        $emailPribadi = strtolower(trim((string) $request->email_pribadi));
        if ($emailPribadi !== '' && !filter_var($emailPribadi, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Format email pribadi tidak valid.'], 422);
        }
        $phone = trim((string) $request->no_telpon);
        $address = trim((string) $request->alamat);
        if ($phone === '' || $address === '') {
            return response()->json(['message' => 'Nomor telepon dan alamat wajib diisi.'], 422);
        }

        $educations = collect($request->input('pendidikan', []))->map(function ($item) {
            return [
                'jenjang' => trim((string) ($item['jenjang'] ?? '')),
                'institusi' => trim((string) ($item['institusi'] ?? '')),
                'jurusan' => trim((string) ($item['jurusan'] ?? '')),
                'tahun_masuk' => trim((string) ($item['tahun_masuk'] ?? '')),
                'tahun_lulus' => trim((string) ($item['tahun_lulus'] ?? '')),
                'kota' => trim((string) ($item['kota'] ?? '')),
            ];
        })->filter(function ($item) {
            return $item['jenjang'] !== '' || $item['institusi'] !== '' || $item['jurusan'] !== '';
        })->values()->all();
        $skills = collect($request->input('skill', []))->map(function ($item) {
            return [
                'keahlian' => trim((string) ($item['keahlian'] ?? '')),
                'rate' => max(1, min(10, (int) ($item['rate'] ?? 1))),
            ];
        })->filter(function ($item) {
            return $item['keahlian'] !== '';
        })->values()->all();

        $allowed = [
            'email_pribadi' => $emailPribadi ?: null,
            'nama_panggilan' => trim((string) $request->nama_panggilan) ?: null,
            'no_telpon' => $phone,
            'kebangsaan' => trim((string) $request->kebangsaan) ?: null,
            'jenis_kelamin' => trim((string) $request->jenis_kelamin) ?: null,
            'agama' => trim((string) $request->agama) ?: null,
            'status_pernikahan' => trim((string) $request->status_pernikahan) ?: null,
            'alamat' => $address,
            'kota' => trim((string) $request->kota) ?: null,
            'provinsi' => trim((string) $request->provinsi) ?: null,
            'negara' => trim((string) $request->negara) ?: null,
            'kode_pos' => trim((string) $request->kode_pos) ?: null,
            'pendidikan' => json_encode($educations),
            'skill' => json_encode($skills),
            'updated_by' => $employee->nama_lengkap,
            'updated_at' => Carbon::now(),
        ];
        if (Schema::hasColumn('master_karyawan', 'nik_kk')) {
            $allowed['nik_kk'] = trim((string) $request->nik_kk) ?: null;
        }

        DB::transaction(function () use ($employee, $attempt, $allowed) {
            DB::table('master_karyawan')->where('id', $employee->id)->update($allowed);
            DB::table('assessment_internal_attempts')->where('id', $attempt->id)->update([
                'profile_completed_at' => Carbon::now(),
                'last_activity_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });

        return response()->json($this->statePayload($attempt->id));
    }

    public function complete(Request $request)
    {
        $attempt = $this->authorizedAttempt($request);
        $state = $this->statePayload($attempt->id);
        if (($state['status'] ?? null) !== 'ready_to_complete') {
            return response()->json(['message' => 'Masih ada soal yang belum dijawab.'], 422);
        }

        if (!$attempt->completion_email_sent_at) {
            $assessment = DB::table('assessment_internal')->where('id', $attempt->assessment_internal_id)->first();
            $this->sendCompletionEmail($attempt, $assessment);
        }

        $now = Carbon::now();
        DB::table('assessment_internal_attempts')->where('id', $attempt->id)->update([
            'status' => 'completed',
            'access_token_hash' => null,
            'last_activity_at' => $now,
            'completed_at' => $now,
            'completion_email_sent_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json(['status' => 'completed', 'message' => 'Assessment berhasil diselesaikan.']);
    }

    private function assessmentByToken($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            abort(404, 'Assessment tidak ditemukan atau belum tersedia. Silakan hubungi HR Department.');
        }

        $assessment = DB::table('assessment_internal')->where('token', $token)->first();
        if (!$assessment || !(bool) $assessment->is_publish || !(bool) $assessment->is_link_active || $assessment->canceled_at) {
            abort(404, 'Assessment tidak ditemukan atau belum tersedia. Silakan hubungi HR Department.');
        }

        return $assessment;
    }

    private function authorizedAttempt(Request $request)
    {
        $assessment = $this->assessmentByToken($request->token);
        $givenHash = hash('sha256', (string) $request->access_token);
        $attempt = DB::table('assessment_internal_attempts')
            ->where('assessment_internal_id', $assessment->id)
            ->where('status', 'in_progress')
            ->where('access_token_hash', $givenHash)
            ->first();

        if (!$attempt || !hash_equals((string) $attempt->access_token_hash, $givenHash)) {
            abort(401, 'Sesi assessment tidak valid. Silakan masukkan email kembali.');
        }

        return $attempt;
    }

    private function ensureSessions($assessment, $attemptId)
    {
        $definitions = json_decode($assessment->category_question ?: '[]', true) ?: [];
        foreach (array_values($definitions) as $index => $definition) {
            $definition = is_array($definition) ? $definition : ['id' => $definition];
            $categoryId = $definition['question_category_id'] ?? $definition['category_id'] ?? $definition['id'] ?? null;
            if (!$categoryId) {
                continue;
            }

            $category = DB::table('question_categories')->where('id', $categoryId)->first();
            if (!$category) {
                continue;
            }

            $sessionExists = DB::table('assessment_internal_sessions')
                ->where('assessment_internal_attempt_id', $attemptId)
                ->where('question_category_id', $categoryId)
                ->exists();
            if ($sessionExists) {
                continue;
            }

            $questionCount = (int) ($definition['question_count'] ?? $definition['jumlah_soal'] ?? 0);
            $hasTimeLimit = array_key_exists('has_time_limit', $definition)
                ? filter_var($definition['has_time_limit'], FILTER_VALIDATE_BOOLEAN)
                : ((int) ($definition['duration_minutes'] ?? 0) > 0);
            $durationMinutes = $hasTimeLimit
                ? (int) ($definition['duration_minutes'] ?? 0)
                : null;
            if ($hasTimeLimit && $durationMinutes <= 0) {
                $durationMinutes = null;
            }

            $questions = $this->sessionQuestions($category, $questionCount);

            if (!$questions) {
                continue;
            }

            DB::table('assessment_internal_sessions')->insert([
                'assessment_internal_attempt_id' => $attemptId,
                'question_category_id' => $categoryId,
                'session_order' => $index + 1,
                'category_name' => $category->name,
                'duration_minutes' => $durationMinutes,
                'questions_json' => json_encode($questions),
                'answers_json' => json_encode(new \stdClass()),
                'result_json' => null,
                'status' => 'pending',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    private function sessionQuestions($category, $questionCount)
    {
        $categoryName = strtoupper(trim((string) $category->name));
        if ($categoryName === 'DISC') {
            return $this->discQuestions($questionCount);
        }
        if (in_array($categoryName, ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return $this->papiQuestions($questionCount);
        }

        $query = DB::table('questions')
            ->where('question_category_id', $category->id)
            ->where('is_active', 1)
            ->whereIn('question_type', ['single_choice', 'multiple_choice', 'scale']);
        if ($questionCount > 0) {
            $query->limit($questionCount);
        }

        return $query->inRandomOrder()->get()->values()->map(function ($question, $questionIndex) {
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
                $values = collect($options)->pluck('value');
                $scaleMin = $values->isNotEmpty() ? (float) $values->min() : 0;
                $scaleMax = $values->isNotEmpty() ? (float) $values->max() : 0;
            }

            $payload = [
                'id' => (string) $question->id,
                'source' => 'question_bank',
                'order' => $questionIndex + 1,
                'type' => $question->question_type,
                'text' => $question->question_text,
                'image' => json_decode($question->question_image ?: '[]', true) ?: [],
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

    private function discQuestions($questionCount)
    {
        $query = DB::table('soal_psikotes')->where('kategori_soal', 'DISC')->orderBy('id');
        if ($questionCount > 0) {
            $query->limit($questionCount);
        }

        return $query->get()->values()->map(function ($question, $index) {
            $prompt = json_decode($question->pertanyaan ?: '{}', true) ?: [];
            $answer = json_decode($question->jawaban ?: '{}', true) ?: [];
            $options = array_values($prompt['data'] ?? []);

            return [
                'id' => (string) $question->id,
                'source' => 'disc',
                'order' => $index + 1,
                'type' => 'disc',
                'text' => 'Pilih satu pernyataan yang paling dan paling tidak menggambarkan diri Anda',
                'options' => collect($options)->map(function ($text, $optionIndex) {
                    return ['id' => (string) $optionIndex, 'text' => $text];
                })->all(),
                'answer_map' => $answer['data'] ?? ['P' => [], 'K' => []],
            ];
        })->all();
    }

    private function papiQuestions($questionCount)
    {
        $query = DB::table('soal_psikotes')->whereIn('kategori_soal', ['KOSTICK PAPI', 'PAPI KOSTICK'])->orderBy('id');
        if ($questionCount > 0) {
            $query->limit($questionCount);
        }

        return $query->get()->values()->map(function ($question, $index) {
            $answer = json_decode($question->jawaban ?: '{}', true) ?: [];
            $options = array_values($answer['data'] ?? []);

            return [
                'id' => (string) $question->id,
                'source' => 'papi_kostick',
                'order' => $index + 1,
                'type' => 'single_choice',
                'text' => '',
                'options' => collect($options)->map(function ($text, $optionIndex) {
                    return [
                        'id' => (string) $optionIndex,
                        'text' => preg_replace('/^[a-zA-Z][\)\.]\s*/', '', $text),
                    ];
                })->all(),
                'answer_map' => array_values($answer['value'] ?? []),
            ];
        })->all();
    }

    private function statePayload($attemptId)
    {
        $attempt = DB::table('assessment_internal_attempts')->where('id', $attemptId)->first();
        $sessions = DB::table('assessment_internal_sessions')
            ->where('assessment_internal_attempt_id', $attemptId)
            ->orderBy('session_order')
            ->get();
        if ($sessions->isEmpty()) {
            return ['status' => 'waiting_data', 'message' => 'Data soal assessment belum tersedia.'];
        }

        $sessionNavigation = $sessions->map(function ($item) {
            return [
                'order' => (int) $item->session_order,
                'name' => $item->category_name,
                'status' => $item->status,
            ];
        })->values()->all();

        $assessment = DB::table('assessment_internal')->where('id', $attempt->assessment_internal_id)->first();
        $requiresProfile = $assessment && (bool) ($assessment->is_completed_profile ?? false);
        $profileOrder = ((int) $sessions->max('session_order')) + 1;
        if ($requiresProfile) {
            $sessionNavigation[] = [
                'order' => $profileOrder,
                'name' => 'Kelengkapan Profil',
                'status' => $attempt->profile_completed_at ? 'completed' : 'pending',
            ];
        }

        if (!$attempt->consent_at) {
            $first = $sessions->first();
            $firstQuestions = json_decode($first->questions_json ?: '[]', true) ?: [];
            return [
                'status' => 'ready',
                'sessions' => $sessionNavigation,
                'session' => [
                    'order' => (int) $first->session_order,
                    'name' => $first->category_name,
                    'duration_minutes' => $first->duration_minutes,
                    'question_count' => count($firstQuestions),
                    'is_first' => true,
                ],
            ];
        }

        $session = $sessions->firstWhere('status', 'in_progress');
        if (!$session) {
            $pending = $sessions->firstWhere('status', 'pending');
            if ($pending) {
                $pendingQuestions = json_decode($pending->questions_json ?: '[]', true) ?: [];
                return [
                    'status' => 'waiting',
                    'sessions' => $sessionNavigation,
                    'session' => [
                        'order' => (int) $pending->session_order,
                        'name' => $pending->category_name,
                        'duration_minutes' => $pending->duration_minutes,
                        'question_count' => count($pendingQuestions),
                        'is_first' => !$sessions->contains(function ($item) {
                            return in_array($item->status, ['completed', 'expired'], true);
                        }),
                    ],
                ];
            }
            if ($requiresProfile && !$attempt->profile_completed_at) {
                $sessionNavigation[count($sessionNavigation) - 1]['status'] = 'in_progress';
                return [
                    'status' => 'profile_required',
                    'sessions' => $sessionNavigation,
                    'session' => ['order' => $profileOrder, 'name' => 'Kelengkapan Profil'],
                    'profile' => $this->employeeProfile($attempt->email),
                ];
            }
            return ['status' => 'ready_to_complete', 'sessions' => $sessionNavigation];
        }

        $questions = collect(json_decode($session->questions_json ?: '[]', true) ?: []);

        if ($session->expires_at && Carbon::parse($session->expires_at)->isPast()) {
            $expiredAnswers = json_decode($session->answers_json ?: '{}', true) ?: [];
            foreach ($questions as $question) {
                $questionId = (string) ($question['id'] ?? '');
                if ($questionId !== '' && !array_key_exists($questionId, $expiredAnswers)) {
                    $expiredAnswers[$questionId] = null;
                }
            }
            $this->finishSession($session, $expiredAnswers, 'expired');
            return $this->statePayload($attemptId);
        }

        $answers = collect(json_decode($session->answers_json ?: '{}', true) ?: []);
        $next = $questions->first(function ($question) use ($answers) {
            return !$answers->has((string) ($question['id'] ?? ''));
        });

        if (!$next) {
            $this->finishSession($session, $answers->all(), 'completed');
            return $this->statePayload($attemptId);
        }

        unset($next['answer_key'], $next['answer_map']);
        foreach ($next['options'] as &$option) {
            unset($option['is_correct']);
        }

        return [
            'status' => 'in_progress',
            'sessions' => $sessionNavigation,
            'session' => [
                'id' => $session->id,
                'order' => $session->session_order,
                'name' => $session->category_name,
                'duration_minutes' => $session->duration_minutes,
                'expires_at' => $session->expires_at,
            ],
            'answered' => $answers->count(),
            'total' => $questions->count(),
            'question' => $next,
            'proctoring' => $this->proctoringPayload($attemptId),
        ];
    }

    private function finishSession($session, array $answers, $status)
    {
        $result = $this->scoreSession($session, $answers);
        $result['status'] = $status;
        $result['scored_at'] = Carbon::now()->toDateTimeString();

        DB::table('assessment_internal_sessions')->where('id', $session->id)->update([
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
        $categoryName = strtoupper(trim((string) $session->category_name));
        if ($categoryName === 'DISC') {
            return $this->scoreDisc($questions, $answers);
        }
        if (in_array($categoryName, ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
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
        return [
            'engine' => 'question_bank',
            'answered' => $answered,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correct,
            'score' => $totalQuestions ? round(($correct / $totalQuestions) * 100, 2) : 0,
        ];
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
        $roles = DB::table('papi_roles')
            ->join('papi_aspects', 'papi_aspects.id', '=', 'papi_roles.aspect_id')
            ->select('papi_roles.id', 'papi_roles.code', 'papi_roles.role', 'papi_aspects.id as aspect_id', 'papi_aspects.aspect as aspect_name')
            ->get()
            ->keyBy('id');
        $aspects = [];
        foreach ($scores as $roleId => $score) {
            $role = $roles[$roleId] ?? null;
            if (!$role) {
                continue;
            }
            $rule = DB::table('papi_rules')
                ->where('role_id', $roleId)
                ->where('low_value', '<=', $score)
                ->where('high_value', '>=', $score)
                ->first();
            if (!isset($aspects[$role->aspect_id])) {
                $aspects[$role->aspect_id] = [
                    'aspect_id' => $role->aspect_id,
                    'aspect_name' => $role->aspect_name,
                    'roles' => [],
                ];
            }
            $aspects[$role->aspect_id]['roles'][] = [
                'role_id' => (int) $role->id,
                'role_code' => $role->code,
                'role_description' => $role->role,
                'score' => $score,
                'interpretation' => $rule->interprestation ?? 'Interpretasi tidak ditemukan',
            ];
        }

        return [
            'engine' => 'papi_kostick',
            'answered' => count($roleIds),
            'total_questions' => count($questions),
            'aspects' => array_values($aspects),
        ];
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
            $result[$aspect] = [
                1 => $mostCounts[$aspect] ?? 0,
                2 => $leastCounts[$aspect] ?? 0,
                3 => $aspect === 'N' ? 0 : (($mostCounts[$aspect] ?? 0) - ($leastCounts[$aspect] ?? 0)),
            ];
        }

        $legacyScorer = app()->make(\App\Http\Controllers\api\EvaluasiKaryawanController::class);
        $profiles = [];
        foreach ([1, 2, 3] as $line) {
            $legacyResult = $legacyScorer->getPattern($result, $line);
            $profiles[] = [
                'line' => $line,
                'scores' => (array) $legacyResult[0],
                'pattern' => isset($legacyResult[1]) && is_object($legacyResult[1]) ? $legacyResult[1]->toArray() : null,
            ];
        }

        return [
            'engine' => 'disc',
            'answered' => count($most),
            'total_questions' => count($questions),
            'raw_scores' => $result,
            'profiles' => $profiles,
        ];
    }

    private function proctoringPayload($attemptId)
    {
        $attempt = DB::table('assessment_internal_attempts')->where('id', $attemptId)->first();
        $events = collect(json_decode($attempt->activity_meta ?? '[]', true) ?: [])
            ->filter(function ($event) {
                return in_array($event['event'] ?? null, ['tab_hidden', 'window_blur'], true);
            })->sortBy('created_at')->values();

        $incidents = [];
        foreach ($events as $event) {
            $eventAt = Carbon::parse($event['created_at']);
            $lastIndex = count($incidents) - 1;
            if ($lastIndex >= 0 && $eventAt->diffInSeconds($incidents[$lastIndex]['carbon_at']) <= 2) {
                $incidents[$lastIndex]['event'] = $event['event'];
                $incidents[$lastIndex]['at'] = $event['created_at'];
                $incidents[$lastIndex]['carbon_at'] = $eventAt;
                continue;
            }
            $incidents[] = [
                'event' => $event['event'],
                'at' => $event['created_at'],
                'carbon_at' => $eventAt,
            ];
        }

        $leaveCount = count($incidents);
        $latest = $leaveCount ? $incidents[$leaveCount - 1] : null;
        $history = array_slice(array_reverse($incidents), 0, 20);
        $history = array_map(function ($event) {
            unset($event['carbon_at']);
            return $event;
        }, $history);

        return [
            'leave_count' => $leaveCount,
            'latest_leave_event' => $latest ? [
                'event' => $latest['event'],
                'at' => $latest['at'],
            ] : null,
            'events' => $history,
        ];
    }

    private function sendRegistrationEmail($employee, $assessment, $assessmentUrl)
    {
        SendEmail::where('to', $employee->email)
            ->where('subject', 'Konfirmasi Pendaftaran Assessment Internal - PT Inti Surya Laboratorium')
            ->where('body', view('Email.internal-assessment-registration', [
                'name' => $employee->nama_lengkap ?? 'Peserta',
                'assessmentName' => $assessment->nama_assesment,
                'assessmentUrl' => $assessmentUrl,
            ])->render())
            ->where('cc', [])
            ->where('bcc', [])
            ->where('karyawan', 'Internal Assessment System')
            ->noReply('PT Inti Surya Laboratorium')
            ->replyToAtsHrd()
            ->send();
    }

    private function sendCompletionEmail($attempt, $assessment)
    {
        SendEmail::where('to', $attempt->email)
            ->where('subject', 'Assessment Internal Telah Selesai - PT Inti Surya Laboratorium')
            ->where('body', view('Email.internal-assessment-completed', [
                'name' => $attempt->participant_name ?: 'Peserta',
                'assessmentName' => $assessment->nama_assesment ?? 'Assessment Internal',
            ])->render())
            ->where('cc', [])
            ->where('bcc', [])
            ->where('karyawan', 'Internal Assessment System')
            ->noReply('PT Inti Surya Laboratorium')
            ->replyToAtsHrd()
            ->send();
    }

    private function storeEvent($attemptId, $sessionId, $event, array $metadata = [])
    {
        $now = Carbon::now()->toDateTimeString();
        DB::update(
            "UPDATE assessment_internal_attempts
             SET activity_meta = JSON_ARRAY_APPEND(
                    COALESCE(activity_meta, JSON_ARRAY()),
                    '$',
                    JSON_OBJECT(
                        'session_id', ?,
                        'event', ?,
                        'metadata', JSON_EXTRACT(?, '$'),
                        'created_at', ?
                    )
                 ),
                 last_activity_at = ?,
                 updated_at = ?
             WHERE id = ?",
            [
                $sessionId,
                $event,
                json_encode($metadata ?: null),
                $now,
                $now,
                $now,
                $attemptId,
            ]
        );
    }

    private function employeeProfile($email)
    {
        $employee = DB::table('master_karyawan')->whereRaw('LOWER(email) = ?', [strtolower((string) $email)])->first();
        if (!$employee) {
            return null;
        }

        return [
            'nama_lengkap' => $employee->nama_lengkap,
            'email' => $employee->email,
            'nik_karyawan' => $employee->nik_karyawan,
            'nik_ktp' => $employee->nik_ktp,
            'nik_kk' => Schema::hasColumn('master_karyawan', 'nik_kk') ? $employee->nik_kk : null,
            'tempat_lahir' => $employee->tempat_lahir,
            'tanggal_lahir' => $employee->tanggal_lahir,
            'email_pribadi' => $employee->email_pribadi,
            'nama_panggilan' => $employee->nama_panggilan,
            'no_telpon' => $employee->no_telpon,
            'kebangsaan' => $employee->kebangsaan,
            'jenis_kelamin' => $employee->jenis_kelamin,
            'agama' => $employee->agama,
            'status_pernikahan' => $employee->status_pernikahan,
            'alamat' => $employee->alamat,
            'kota' => $employee->kota,
            'provinsi' => $employee->provinsi,
            'negara' => $employee->negara,
            'kode_pos' => $employee->kode_pos,
            'pendidikan' => json_decode($employee->pendidikan ?: '[]', true) ?: [],
            'skill' => json_decode($employee->skill ?: '[]', true) ?: [],
        ];
    }
}
