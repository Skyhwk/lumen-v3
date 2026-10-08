<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\AssessmentInternal;
use App\Models\MasterKaryawan;
use App\Models\QuestionCategory;
use App\Jobs\GenerateInternalAssessmentParticipantPdfJob;
use App\Jobs\GenerateInternalAssessmentPdfBatchJob;
use App\Services\InternalAssessmentExcelExportService;
use App\Services\InternalAssessmentParticipantPdfService;

class AssessmentInternalController extends Controller
{
    use Concerns\BuildsCandidateAssessmentPreview;
    public function index(Request $request)
    {
        $data = AssessmentInternal::query()
            ->select('assessment_internal.*')
            ->selectRaw('(
                SELECT COUNT(*)
                FROM assessment_internal_attempts
                WHERE assessment_internal_attempts.assessment_internal_id = assessment_internal.id
            ) as participants_count')
            ->orderBy('id', 'desc');

        return datatables()->of($data)->make(true);
    }

    public function store(Request $request)
    {
        try {
            if (empty($request->nama_assesment)) {
                return response()->json(['message' => 'Nama Assessment harus diisi!'], 400);
            }
            $nama = $request->nama_assesment;
            
            // Cek apakah assessment dengan nama tersebut sudah ada
            $exists = AssessmentInternal::where('nama_assesment', $nama)->first();
            if ($exists) {
                return response()->json(['message' => 'Assessment dengan nama "' . $nama . '" sudah dibuat sebelumnya!'], 400);
            }

            // Generate string unik 8 karakter huruf kapital dan angka
            $pool = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $batch = substr(str_shuffle(str_repeat($pool, 8)), 0, 8);
            
            while (AssessmentInternal::where('batch', $batch)->exists()) {
                $batch = substr(str_shuffle(str_repeat($pool, 8)), 0, 8);
            }

            $hrdName = $this->karyawan ?? 'HRD';

            $assessment = new AssessmentInternal();
            $assessment->batch = $batch;
            $assessment->nama_assesment = $nama;
            $assessment->link_qr = null;
            $assessment->created_by = $hrdName;
            
            // Mengisi timestamps secara manual karena di model di-set public $timestamps = false
            $assessment->created_at = date('Y-m-d H:i:s');
            $assessment->updated_at = date('Y-m-d H:i:s');
            
            $assessment->save();

            return response()->json(['message' => 'Assessment "' . $nama . '" berhasil dibuat dengan Batch ' . $batch], 200);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1] ?? null;
            if ($errorCode == 1062) {
                return response()->json(['message' => 'Gagal membuat assessment: Duplikasi Batch. Silakan coba lagi.'], 400);
            }
            return response()->json(['message' => 'Terjadi kesalahan database: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function updateLink(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            // Kata spesial sebagai key encrypt/decrypt
            $secretKey = 'anak kuat yang tangguh ini juga sering error';
            
            // Hash key dengan sha256 agar menjadi 32 bytes (syarat aes-256)
            $key = hash('sha256', $secretKey, true);
            
            // Encrypt batch dengan AES-256-ECB lalu convert ke Hex agar terbebas dari karakter spesial (hanya angka & huruf)
            $encrypted = openssl_encrypt($assessment->batch, 'aes-256-ecb', $key, OPENSSL_RAW_DATA);
            $token = bin2hex($encrypted); // Panjangnya akan statis 32 karakter alfanumerik

            // Format URL Assessment
            $baseUrl = env('PORTALV4');
            $assessment->link_qr = $baseUrl . 'private/assessment/' . $token;
            $assessment->is_link_active = true;
            
            $assessment->save();

            return response()->json(['message' => 'Link berhasil di-generate secara otomatis!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function takedownLink(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $assessment->is_link_active = false;
            $assessment->link_deactivated_at = date('Y-m-d H:i:s');
            $assessment->save();

            return response()->json(['message' => 'Link assessment berhasil di-take down!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper Function: Contoh cara decrypt token dari URL nantinya
     */
    public function decryptToken($token)
    {
        $secretKey = 'anak kuat yang tangguh ini juga sering error';
        $key = hash('sha256', $secretKey, true);
        
        // Convert hex kembali ke binary, lalu decrypt
        $batch = openssl_decrypt(hex2bin($token), 'aes-256-ecb', $key, OPENSSL_RAW_DATA);
        
        return $batch;
    }

    public function getCategories(Request $request)
    {
        try {
            $categories = QuestionCategory::withCount([
                'questions as current_question_count' => function ($query) {
                    $query->where('question_scope', 'hr')->where('status', '!=', 'retired');
                },
            ])
                ->where('is_active', true)
                ->where(function ($builder) {
                    $builder->whereIn('category_scope', ['hr', 'default'])->orWhereNull('category_scope');
                })
                ->orderByRaw("CASE WHEN UPPER(name) = 'DISC' THEN 1 WHEN UPPER(name) IN ('KOSTICK PAPI', 'PAPI KOSTICK') THEN 2 ELSE 3 END")
                ->orderBy('name')
                ->get();

            return response()->json(['data' => $categories], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function getGrades(Request $request)
    {
        try {
            $grades = MasterKaryawan::query()
                ->where('is_active', 1)
                ->whereNotNull('grade')
                ->whereRaw("TRIM(grade) <> ''")
                ->selectRaw('TRIM(grade) as grade')
                ->distinct()
                ->orderBy('grade')
                ->pluck('grade')
                ->map(function ($grade) {
                    $grade = trim((string) $grade);

                    return [
                        'value' => $grade,
                        'text' => $grade,
                    ];
                })
                ->values();

            return response()->json(['data' => $grades], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memuat daftar grade: ' . $e->getMessage()], 500);
        }
    }

    public function publish(Request $request)
    {
        try {
            if (empty($request->category_question) || !is_array($request->category_question)) {
                return response()->json(['message' => 'Kategori soal harus dipilih!'], 400);
            }

            $normalizedCategories = [];
            foreach ($request->category_question as $item) {
                if (is_numeric($item)) {
                    return response()->json([
                        'message' => 'Format kategori tidak valid. Kirim objek berisi id, question_count, duration_minutes, dan has_time_limit.',
                    ], 400);
                }

                if (!is_array($item) || empty($item['id'])) {
                    continue;
                }

                $category = QuestionCategory::find($item['id']);
                $isMandatory = $category && in_array(strtoupper(trim($category->name)), ['DISC', 'KOSTICK PAPI', 'PAPI KOSTICK'], true);

                $normalizedCategories[] = [
                    'id' => (int) $item['id'],
                    'question_count' => $isMandatory
                        ? (int) ($item['question_count'] ?? 0)
                        : max(1, (int) ($item['question_count'] ?? 1)),
                    'duration_minutes' => max(1, (int) ($item['duration_minutes'] ?? 15)),
                    'has_time_limit' => filter_var($item['has_time_limit'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            if (empty($normalizedCategories)) {
                return response()->json(['message' => 'Minimal pilih 1 kategori soal!'], 400);
            }

            $grade = trim((string) $request->input('grade'));
            if ($grade === '') {
                return response()->json(['message' => 'Grade peserta wajib dipilih!'], 422);
            }

            $gradeExists = MasterKaryawan::query()
                ->where('is_active', 1)
                ->whereRaw('TRIM(grade) = ?', [$grade])
                ->exists();
            if (!$gradeExists) {
                return response()->json(['message' => 'Grade yang dipilih tidak ditemukan pada master karyawan aktif.'], 422);
            }

            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            // 1. Simpan Kategori Soal & Pengaturan Profil
            $assessment->category_question = $normalizedCategories;
            $assessment->grade = $grade;
            if ($request->has('is_completed_profile')) {
                $assessment->is_completed_profile = filter_var($request->is_completed_profile, FILTER_VALIDATE_BOOLEAN);
            }

            // 2. Generate Token & Link jika belum ada
            if (empty($assessment->link_qr)) {
                $secretKey = 'anak kuat yang tangguh ini juga sering error';
                $key = hash('sha256', $secretKey, true);
                $encrypted = openssl_encrypt($assessment->batch, 'aes-256-ecb', $key, OPENSSL_RAW_DATA);
                $token = bin2hex($encrypted);
                
                $baseUrl = env('PORTALV4');
                $assessment->token = $token;
                $assessment->link_qr = $baseUrl . 'private/assessment/' . $token;
                $assessment->is_link_active = true;
            }

            // 3. Generate File Gambar QR Code jika belum ada
            if (empty($assessment->image_qr)) {
                $fileName = 'QR_' . $assessment->batch . '_' . time() . '.png';
                $path = base_path('public/QR_Assessment');
                if (!file_exists($path)) {
                    mkdir($path, 0775, true);
                }
                \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->generate($assessment->link_qr, $path . '/' . $fileName);
                $assessment->image_qr = $fileName;
            }

            // 4. Ubah Status
            $assessment->is_publish = true;
            $assessment->save();

            return response()->json(['message' => 'Assessment berhasil dipublish (Link & QR telah di-generate)!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function cancel(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $participantCount = DB::table('assessment_internal_attempts')
                ->where('assessment_internal_id', $assessment->id)
                ->count();

            if ($participantCount > 0) {
                return response()->json([
                    'message' => 'Assessment tidak dapat dibatalkan karena sudah ada peserta yang masuk.',
                ], 400);
            }

            $hrdName = $this->karyawan ?? 'HRD';

            $assessment->canceled_by = $hrdName;
            $assessment->canceled_at = date('Y-m-d H:i:s');
            $assessment->save();

            return response()->json(['message' => 'Assessment berhasil dibatalkan'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function generateQr(Request $request)
    {
        try {
            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            if (empty($assessment->link_qr)) {
                return response()->json(['message' => 'Link QR tidak tersedia, tidak bisa di-generate.'], 400);
            }

            $fileName = 'QR_' . $assessment->batch . '_' . time() . '.png';
            $path = base_path('public/QR_Assessment');
            
            if (!file_exists($path)) {
                mkdir($path, 0775, true);
            }

            \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->generate($assessment->link_qr, $path . '/' . $fileName);

            $assessment->image_qr = $fileName;
            $assessment->save();

            return response()->json(['message' => 'QR Code berhasil di-generate', 'file' => $fileName], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    protected function buildInternalAssessmentData($attempt, $sessions)
    {
        $sessionData = [];
        $totalAnswered = 0;
        $totalQuestions = 0;

        foreach ($sessions as $session) {
            $answered = $this->countAnsweredQuestions($session->answers_json);
            $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
            $questionCount = count($questions);

            $totalAnswered += $answered;
            $totalQuestions += $questionCount;

            $targetName = $this->resolveEvaluationTargetName($session);
            $categoryName = $session->category_name ?? 'Kategori Soal';

            $sessionData[] = [
                'id' => (int) $session->id,
                'order' => (int) ($session->session_order ?? 1),
                'name' => $categoryName,
                'display_name' => $this->internalSessionDisplayName($session),
                'target_name' => $targetName !== '' ? $targetName : null,
                'evaluation_target_karyawan_id' => isset($session->evaluation_target_karyawan_id)
                    ? (int) $session->evaluation_target_karyawan_id
                    : null,
                'status' => $session->status ?? 'pending',
                'answered' => $answered,
                'total' => $questionCount,
                'progress_percent' => $questionCount > 0 ? round(($answered / $questionCount) * 100) : 0,
                'has_result' => !empty($session->result_json),
                'use_progress_metric' => $this->internalSessionUsesProgressMetric($session),
                'score_preview' => $this->extractInternalSessionScorePreview($session),
                'duration_minutes' => (int) ($session->duration_minutes ?? 0),
                'started_at' => $session->started_at,
                'completed_at' => $session->completed_at,
            ];
        }

        usort($sessionData, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        $summary = 'Assessment belum dimulai';
        if (($attempt->status ?? '') === 'completed') {
            $summary = 'Assessment selesai';
        } elseif (($attempt->status ?? '') === 'in_progress') {
            $summary = 'Assessment sedang berlangsung';
            foreach ($sessions as $session) {
                if (($session->status ?? '') === 'in_progress') {
                    $answered = $this->countAnsweredQuestions($session->answers_json);
                    $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
                    $total = count($questions);
                    $summary = 'Sedang mengerjakan ' . ($session->category_name ?? 'sesi')
                        . ' (' . $answered . '/' . $total . ' soal)';
                    break;
                }
                if (($session->status ?? '') === 'pending') {
                    $summary = 'Menunggu sesi ' . ($session->category_name ?? 'berikutnya');
                    break;
                }
            }
        }

        return [
            'summary' => $summary,
            'total_answered' => $totalAnswered,
            'total_questions' => $totalQuestions,
            'attempt_status' => $attempt->status ?? 'in_progress',
            'overall_progress' => $totalQuestions > 0 ? round(($totalAnswered / $totalQuestions) * 100) : 0,
            'sessions' => $sessionData,
        ];
    }

    public function getParticipants(Request $request)
    {
        try {
            $assessmentId = $request->input('assessment_id') ?? $request->id;
            $assessment = DB::table('assessment_internal')->where('id', $assessmentId)->first();
            if (!$assessment) {
                return response()->json(['success' => false, 'message' => 'Assessment tidak ditemukan.'], 404);
            }

            $attempts = DB::table('assessment_internal_attempts')
                ->where('assessment_internal_id', $assessmentId)
                ->orderByDesc('id')
                ->get();

            $emailList = $attempts->pluck('email')->filter()->map(function ($email) {
                return strtolower(trim((string) $email));
            })->unique()->values()->all();

            $karyawanByEmail = [];
            if (!empty($emailList)) {
                $karyawanRows = DB::table('master_karyawan as karyawan')
                    ->leftJoin('master_divisi as divisi', 'divisi.id', '=', 'karyawan.id_department')
                    ->where(function ($query) use ($emailList) {
                        foreach ($emailList as $email) {
                            $query->orWhereRaw('LOWER(TRIM(karyawan.email)) = ?', [$email]);
                        }
                    })
                    ->get(['karyawan.id', 'karyawan.email', 'divisi.nama_divisi']);

                foreach ($karyawanRows as $row) {
                    $karyawanByEmail[strtolower(trim((string) $row->email))] = [
                        'id' => (int) $row->id,
                        'nama_divisi' => $row->nama_divisi,
                    ];
                }
            }

            $participantsMap = [];
            foreach ($attempts as $attempt) {
                $emailKey = strtolower(trim((string) ($attempt->email ?? '')));
                $participantsMap[$attempt->id] = [
                    'id' => (int) $attempt->id,
                    'karyawan_id' => $karyawanByEmail[$emailKey]['id'] ?? null,
                    'nama_divisi' => $karyawanByEmail[$emailKey]['nama_divisi'] ?? null,
                    'nama_lengkap' => $attempt->participant_name ?? 'Unknown',
                    'nik' => $attempt->email ?? '-',
                    'status' => $attempt->status ?? 'in_progress',
                    'progress' => 0,
                    'started_at' => $attempt->started_at,
                    'assessment_data' => $this->buildInternalAssessmentData($attempt, collect()),
                ];
            }

            $attemptIds = array_keys($participantsMap);

            if (!empty($attemptIds)) {
                $sessions = DB::table('assessment_internal_sessions')
                    ->whereIn('assessment_internal_attempt_id', $attemptIds)
                    ->orderBy('session_order')
                    ->get()
                    ->groupBy('assessment_internal_attempt_id');

                foreach ($participantsMap as $attemptId => &$participant) {
                    $attemptSessions = $sessions->get($attemptId, collect());
                    $attempt = $attempts->firstWhere('id', $attemptId);
                    $participant['assessment_data'] = $this->buildInternalAssessmentData($attempt, $attemptSessions);
                    $participant['progress'] = $participant['assessment_data']['overall_progress'];
                }
                unset($participant);
            }

            // Penilaian terhadap peserta bisa berasal dari batch Staff/SPV lain
            // pada tahun yang sama. Hanya dua kategori penilaian silang ini yang digabung.
            $incomingParticipants = [];
            $participantKaryawanIds = array_values(array_unique(array_filter(array_column($participantsMap, 'karyawan_id'))));
            $assessmentYear = (int) substr((string) $assessment->created_at, 0, 4);
            if (!empty($participantKaryawanIds) && $assessmentYear > 0) {
                $incomingSessions = DB::table('assessment_internal_sessions as session')
                    ->join('assessment_internal_attempts as attempt', 'attempt.id', '=', 'session.assessment_internal_attempt_id')
                    ->join('assessment_internal as source', 'source.id', '=', 'attempt.assessment_internal_id')
                    ->where('source.is_publish', 1)
                    ->where('source.is_link_active', 1)
                    ->whereNull('source.canceled_at')
                    ->where('source.created_at', '>=', $assessmentYear . '-01-01 00:00:00')
                    ->where('source.created_at', '<', ($assessmentYear + 1) . '-01-01 00:00:00')
                    ->whereIn('session.evaluation_target_karyawan_id', $participantKaryawanIds)
                    ->whereRaw('UPPER(TRIM(session.category_name)) IN (?, ?)', [
                        'SATISFACTION OF LEADER', 'EMPLOYEE EVALUATION',
                    ])
                    ->get([
                        'session.*',
                        'attempt.id as rater_attempt_id',
                        'attempt.participant_name as rater_name',
                    ]);

                foreach ($incomingSessions as $session) {
                    $raterAttemptId = (int) $session->rater_attempt_id;
                    if (!isset($incomingParticipants[$raterAttemptId])) {
                        $incomingParticipants[$raterAttemptId] = [
                            'id' => $raterAttemptId,
                            'nama_lengkap' => $session->rater_name ?? '-',
                            'assessment_data' => ['sessions' => []],
                        ];
                    }
                    $incomingParticipants[$raterAttemptId]['assessment_data']['sessions'][] = [
                        'id' => (int) $session->id,
                        'name' => $session->category_name,
                        'display_name' => $this->internalSessionDisplayName($session),
                        'evaluation_target_karyawan_id' => (int) $session->evaluation_target_karyawan_id,
                        'status' => $session->status,
                        'score_preview' => $this->extractInternalSessionScorePreview($session),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($participantsMap),
                'incoming_participants' => array_values($incomingParticipants),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch participants: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function candidateSessionResult(Request $request)
    {
        try {
            $sessionId = $request->input('session_id') ?? $request->id;

            if (!$sessionId) {
                return response()->json(['success' => false, 'message' => 'Parameter session_id wajib diisi'], 400);
            }

            $session = DB::table('assessment_internal_sessions')
                ->where('id', $sessionId)
                ->first();

            if (!$session) {
                return response()->json(['success' => false, 'message' => 'Session not found'], 404);
            }

            $sessionDisplayName = $this->internalSessionDisplayName($session);
            $targetName = $this->resolveEvaluationTargetName($session);

            if (empty($session->result_json)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'session_id' => (int) $session->id,
                        'session_name' => $sessionDisplayName,
                        'session_order' => (int) $session->session_order,
                        'status' => $session->status,
                        'target_name' => $targetName !== '' ? $targetName : null,
                        'has_result' => false,
                        'summary_text' => $session->status === 'completed'
                            ? 'Sesi selesai, namun hasil belum tersedia.'
                            : 'Sesi belum selesai — hasil belum tersedia.',
                        'items' => $targetName !== '' ? [[
                            'label' => $this->evaluationTargetItemLabel($session),
                            'value' => $targetName,
                        ]] : [],
                        'scored_at' => null,
                    ],
                ], 200);
            }

            $result = json_decode($session->result_json, true) ?: [];
            $summary = $this->buildSessionResultSummary($session, $result);

            return response()->json([
                'success' => true,
                'data' => array_merge([
                    'session_id' => (int) $session->id,
                    'session_name' => $summary['session_display_name'] ?? $sessionDisplayName,
                    'session_order' => (int) $session->session_order,
                    'status' => $session->status,
                    'has_result' => true,
                ], $summary),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session result: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportParticipantReport(Request $request)
    {
        try {
            $attemptId = (int) ($request->input('attempt_id') ?? $request->id);
            if (!$attemptId) {
                return response()->json(['message' => 'Parameter attempt_id wajib diisi'], 400);
            }

            $payload = app(InternalAssessmentExcelExportService::class)->exportForAttempt($attemptId);

            return response()->json($payload, 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal export Excel: ' . $e->getMessage()], 500);
        }
    }

    public function exportAssessmentReport(Request $request)
    {
        try {
            $assessmentId = (int) ($request->input('assessment_id') ?? $request->id);
            if (!$assessmentId) {
                return response()->json(['message' => 'Parameter assessment_id wajib diisi'], 400);
            }

            $payload = app(InternalAssessmentExcelExportService::class)->exportAssessmentSummary($assessmentId);

            return response()->json($payload, 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal export Excel: ' . $e->getMessage()], 500);
        }
    }

    /** PDF jawaban per sesi (Logika, Nalar, Integritas) per peserta. */
    public function exportParticipantPdf(Request $request)
    {
        try {
            $attemptId = (int) ($request->input('attempt_id') ?? $request->id);
            if (!$attemptId) {
                return response()->json(['message' => 'Parameter attempt_id wajib diisi'], 400);
            }

            $payload = app(InternalAssessmentParticipantPdfService::class)->generateForAttempt($attemptId);

            return response()->json($payload, 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal export PDF: ' . $e->getMessage()], 500);
        }
    }

    /** Queue worker: generate PDF semua peserta dalam batch assessment. */
    public function queueAssessmentParticipantPdfs(Request $request)
    {
        try {
            $assessmentId = (int) ($request->input('assessment_id') ?? $request->id);
            if (!$assessmentId) {
                return response()->json(['message' => 'Parameter assessment_id wajib diisi'], 400);
            }

            $exists = DB::table('assessment_internal')->where('id', $assessmentId)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Assessment tidak ditemukan.'], 404);
            }

            $this->dispatch(new GenerateInternalAssessmentPdfBatchJob($assessmentId));

            return response()->json([
                'message' => 'Generate PDF Nalar & Logika untuk semua peserta sedang diproses di worker.',
                'assessment_id' => $assessmentId,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengantre PDF: ' . $e->getMessage()], 500);
        }
    }

    /** Queue worker: generate PDF satu peserta. */
    public function queueParticipantPdf(Request $request)
    {
        try {
            $attemptId = (int) ($request->input('attempt_id') ?? $request->id);
            if (!$attemptId) {
                return response()->json(['message' => 'Parameter attempt_id wajib diisi'], 400);
            }

            $exists = DB::table('assessment_internal_attempts')->where('id', $attemptId)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Peserta tidak ditemukan.'], 404);
            }

            $this->dispatch(new GenerateInternalAssessmentParticipantPdfJob($attemptId));

            return response()->json([
                'message' => 'Generate PDF peserta sedang diproses di worker.',
                'attempt_id' => $attemptId,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengantre PDF: ' . $e->getMessage()], 500);
        }
    }
}
