<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $attempt = $this->authorizedAttempt($request);
        $session = DB::table('assessment_internal_sessions')
            ->where('assessment_internal_attempt_id', $attempt->id)
            ->where('status', 'in_progress')
            ->orderBy('session_order')
            ->first();
        if (!$session) {
            return response()->json(['message' => 'Sesi assessment tidak aktif.'], 409);
        }

        $questions = collect(json_decode($session->questions_json ?: '[]', true) ?: []);
        $question = $questions->first(function ($item) use ($request) {
            return (string) ($item['id'] ?? '') === (string) $request->question_id;
        });
        if (!$question) {
            return response()->json(['message' => 'Soal tidak ditemukan pada sesi aktif.'], 404);
        }
        if ($request->input('answer') === null || $request->input('answer') === '') {
            return response()->json(['message' => 'Jawaban wajib diisi.'], 422);
        }

        $answer = $request->input('answer');
        DB::table('assessment_internal_answers')->updateOrInsert(
            [
                'assessment_internal_attempt_id' => $attempt->id,
                'question_id' => (string) $question['id'],
            ],
            [
                'assessment_internal_session_id' => $session->id,
                'answer_json' => json_encode($answer),
                'is_correct' => null,
                'score' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
        DB::table('assessment_internal_attempts')->where('id', $attempt->id)->update([
            'last_activity_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json($this->statePayload($attempt->id));
    }

    public function updateProfile(Request $request)
    {
        $attempt = $this->authorizedAttempt($request);
        $assessment = DB::table('assessment_internal')->where('id', $attempt->assessment_internal_id)->first();
        if (!$assessment || !(bool) ($assessment->is_completed_profile ?? false)) {
            return response()->json(['message' => 'Sesi kelengkapan profil tidak tersedia.'], 404);
        }
        if (DB::table('assessment_internal_sessions')->where('assessment_internal_attempt_id', $attempt->id)->where('status', '!=', 'completed')->exists()) {
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
        if (DB::table('assessment_internal_sessions')->where('assessment_internal_attempt_id', $attemptId)->exists()) {
            return;
        }

        $definitions = json_decode($assessment->category_question ?: '[]', true) ?: [];
        foreach (array_values($definitions) as $index => $definition) {
            $definition = is_array($definition) ? $definition : ['question_category_id' => $definition];
            $categoryId = $definition['question_category_id'] ?? $definition['category_id'] ?? $definition['id'] ?? null;
            if (!$categoryId) {
                continue;
            }

            $category = DB::table('question_categories')->where('id', $categoryId)->first();
            if (!$category) {
                continue;
            }

            $limit = (int) ($definition['question_count'] ?? $definition['jumlah_soal'] ?? $category->question_count ?? 0);
            $query = DB::table('questions')
                ->where('question_category_id', $categoryId)
                ->where('is_active', 1)
                ->whereIn('question_type', ['single_choice', 'multiple_choice', 'scale']);
            if ($limit > 0) {
                $query->limit($limit);
            }
            $questions = $query->inRandomOrder()->get()->values()->map(function ($question, $questionIndex) {
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

                return [
                    'id' => (string) $question->id,
                    'order' => $questionIndex + 1,
                    'type' => $question->question_type,
                    'text' => $question->question_text,
                    'image' => json_decode($question->question_image ?: '[]', true) ?: [],
                    'options' => $options,
                    'answer_key' => collect($options)->where('is_correct', true)->pluck('id')->values()->all(),
                ];
            })->all();

            if (!$questions) {
                continue;
            }

            DB::table('assessment_internal_sessions')->insert([
                'assessment_internal_attempt_id' => $attemptId,
                'question_category_id' => $categoryId,
                'session_order' => $index + 1,
                'category_name' => $category->name,
                'duration_minutes' => $definition['duration_minutes'] ?? $category->duration_minutes ?? null,
                'questions_json' => json_encode($questions),
                'status' => 'pending',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
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
                        'is_first' => !$sessions->contains('status', 'completed'),
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

        if ($session->expires_at && Carbon::parse($session->expires_at)->isPast()) {
            DB::table('assessment_internal_sessions')->where('id', $session->id)->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            return $this->statePayload($attemptId);
        }

        $questions = collect(json_decode($session->questions_json ?: '[]', true) ?: []);
        $answers = DB::table('assessment_internal_answers')
            ->where('assessment_internal_session_id', $session->id)
            ->pluck('answer_json', 'question_id');
        $next = $questions->first(function ($question) use ($answers) {
            return !$answers->has((string) ($question['id'] ?? ''));
        });

        if (!$next) {
            DB::table('assessment_internal_sessions')->where('id', $session->id)->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            return $this->statePayload($attemptId);
        }

        unset($next['answer_key']);
        foreach ($next['options'] as &$option) {
            unset($option['is_correct']);
        }

        return [
            'status' => 'in_progress',
            'sessions' => $sessionNavigation,
            'session' => [
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
