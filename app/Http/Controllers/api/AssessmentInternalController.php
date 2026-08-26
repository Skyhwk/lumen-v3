<?php
namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\AssessmentInternal;
use App\Models\QuestionCategory;

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
            $assessment->link_qr = $baseUrl . '/private/assessment/' . $token;
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
                    $builder->where('category_scope', 'hr')->orWhereNull('category_scope');
                })
                ->orderByRaw("CASE WHEN UPPER(name) = 'DISC' THEN 1 WHEN UPPER(name) IN ('KOSTICK PAPI', 'PAPI KOSTICK') THEN 2 ELSE 3 END")
                ->orderBy('name')
                ->get();

            return response()->json(['data' => $categories], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
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
                        : max(30, (int) ($item['question_count'] ?? 30)),
                    'duration_minutes' => max(1, (int) ($item['duration_minutes'] ?? 15)),
                    'has_time_limit' => filter_var($item['has_time_limit'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            if (empty($normalizedCategories)) {
                return response()->json(['message' => 'Minimal pilih 1 kategori soal!'], 400);
            }

            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            // 1. Simpan Kategori Soal & Pengaturan Profil
            $assessment->category_question = $normalizedCategories;
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
                $assessment->link_qr = $baseUrl . '/private/assessment/' . $token;
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

            $sessionData[] = [
                'id' => (int) $session->id,
                'order' => (int) ($session->session_order ?? 1),
                'name' => $session->category_name ?? 'Kategori Soal',
                'status' => $session->status ?? 'pending',
                'answered' => $answered,
                'total' => $questionCount,
                'progress_percent' => $questionCount > 0 ? round(($answered / $questionCount) * 100) : 0,
                'has_result' => !empty($session->result_json),
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

            $attempts = DB::table('assessment_internal_attempts')
                ->where('assessment_internal_id', $assessmentId)
                ->orderByDesc('id')
                ->get();

            $participantsMap = [];
            foreach ($attempts as $attempt) {
                $participantsMap[$attempt->id] = [
                    'id' => (int) $attempt->id,
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

            return response()->json([
                'success' => true,
                'data' => array_values($participantsMap),
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

            if (empty($session->result_json)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'session_id' => (int) $session->id,
                        'session_name' => $session->category_name,
                        'session_order' => (int) $session->session_order,
                        'status' => $session->status,
                        'has_result' => false,
                        'summary_text' => $session->status === 'completed'
                            ? 'Sesi selesai, namun hasil belum tersedia.'
                            : 'Sesi belum selesai — hasil belum tersedia.',
                        'items' => [],
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
                    'session_name' => $session->category_name,
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
}