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
    public function index(Request $request)
    {
        $data = AssessmentInternal::query()->orderBy('id', 'desc');

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
            $categories = QuestionCategory::where('category_scope', 'hr')->get(['id', 'name']);
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

            $assessment = AssessmentInternal::find($request->id);
            if (!$assessment) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            // 1. Simpan Kategori Soal & Pengaturan Profil
            $assessment->category_question = $request->category_question;
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

    public function getParticipants(Request $request)
    {
        try {
            $assessmentId = $request->input('assessment_id') ?? $request->id;
            
            // Fetch attempts (participants)
            $attempts = DB::table('assessment_internal_attempts')
                ->where('assessment_internal_id', $assessmentId)
                ->get();

            $participantsMap = [];
            foreach ($attempts as $attempt) {
                $candidateId = $attempt->id; // Use attempt ID as unique candidate identifier
                
                $participantsMap[$candidateId] = [
                    'id' => $candidateId,
                    'nama_lengkap' => $attempt->participant_name ?? 'Unknown',
                    'nik' => $attempt->email ?? '-', // Display email since nik is not in table
                    'status' => $attempt->status ?? 'in_progress',
                    'progress' => 0,
                    'started_at' => $attempt->started_at,
                    'assessment_data' => [
                        'summary' => 'Assessment Internal',
                        'total_answered' => 0,
                        'total_questions' => 0,
                        'attempt_status' => $attempt->status ?? 'in_progress',
                        'overall_progress' => 0,
                        'sessions' => []
                    ]
                ];
            }

            // Fetch sessions for all these attempts
            $attemptIds = array_keys($participantsMap);
            
            if (!empty($attemptIds)) {
                $sessions = DB::table('assessment_internal_sessions')
                    ->whereIn('assessment_internal_attempt_id', $attemptIds)
                    ->get();

                    foreach ($sessions as $session) {
                        $candidateId = $session->assessment_internal_attempt_id;
                        
                        $resultData = json_decode($session->result_json ?? '{}', true) ?? [];
                        
                        $totalQ = $resultData['total_questions'] ?? 0;
                        $answeredQ = $resultData['answered'] ?? 0;
                        
                        $progressPct = $totalQ > 0 ? round(($answeredQ / $totalQ) * 100) : 0;

                        $participantsMap[$candidateId]['assessment_data']['sessions'][] = [
                            'id' => $session->id,
                            'order' => $session->session_order ?? 1,
                            'name' => $session->category_name ?? 'Kategori Soal',
                            'status' => $session->status,
                            'progress_percent' => $progressPct,
                            'answered' => $answeredQ,
                            'total' => $totalQ,
                            'has_result' => ($session->status === 'completed')
                        ];
                        
                        $participantsMap[$candidateId]['assessment_data']['total_answered'] += $answeredQ;
                        $participantsMap[$candidateId]['assessment_data']['total_questions'] += $totalQ;
                    }
                }
            

            // Calculate overall progress
            foreach ($participantsMap as &$p) {
                $totalQ = $p['assessment_data']['total_questions'];
                $totalA = $p['assessment_data']['total_answered'];
                $p['progress'] = $totalQ > 0 ? round(($totalA / $totalQ) * 100) : 0;
                $p['assessment_data']['overall_progress'] = $p['progress'];
            }

            return response()->json([
                'success' => true,
                'data' => array_values($participantsMap)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch participants: ' . $e->getMessage()
            ], 500);
        }
    }

    public function candidateSessionResult(Request $request)
    {
        try {
            $sessionId = $request->input('session_id') ?? $request->id;
            
            $session = DB::table('assessment_internal_sessions')
                ->where('id', $sessionId)
                ->first();

            if (!$session) {
                return response()->json(['success' => false, 'message' => 'Session not found'], 404);
            }

            $resultJson = json_decode($session->result_json ?? '{}', true) ?? [];
            $engine = $resultJson['engine'] ?? 'generic';

            // Jika DISC atau PAPI Kostick, hasil detail ada di $resultJson
            if ($engine === 'disc' || $engine === 'papi_kostick') {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_result' => true,
                        'engine' => $engine,
                        'summary_text' => 'Test Result - ' . ($session->category_name ?? 'Sesi Ujian'),
                        'disc_detail' => $engine === 'disc' ? $resultJson : null,
                        'papi_detail' => $engine === 'papi_kostick' ? $resultJson : null,
                        'scored_at' => $session->completed_at ?? $session->updated_at
                    ]
                ]);
            }

            // Untuk generic (seperti NALAR, LOGIKA, INTEGRITAS / question_bank)
            $totalScore = $resultJson['score'] ?? 0;
            $correctAnswers = $resultJson['correct_answers'] ?? 0;
            $totalQuestions = $resultJson['total_questions'] ?? 0;

            $items = [
                [
                    'label' => 'Skor',
                    'value' => round((float)$totalScore, 2) . '/100'
                ],
                [
                    'label' => 'Jawaban Benar',
                    'value' => $correctAnswers . '/' . $totalQuestions
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'has_result' => true,
                    'engine' => 'generic',
                    'summary_text' => 'Test Result - ' . ($session->category_name ?? 'Sesi Ujian'),
                    'score' => $totalScore,
                    'items' => $items,
                    'scored_at' => $session->completed_at ?? $session->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session result: ' . $e->getMessage()
            ], 500);
        }
    }
}