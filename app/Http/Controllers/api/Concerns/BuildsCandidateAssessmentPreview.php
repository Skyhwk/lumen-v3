<?php

namespace App\Http\Controllers\api\Concerns;

use App\Models\NewRecruitment;
use App\Services\RecruitmentPictureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait BuildsCandidateAssessmentPreview
{
    protected function recruitmentStatusLabel($status)
    {
        $labels = [
            'assessment' => 'Assessment',
            'screening' => 'Screening HRD',
            'interview_hrd' => 'Interview HRD',
            'profile_completion' => 'Lengkapi Profil',
            'interview_user' => 'Interview User',
            'management_decision' => 'Keputusan Manajemen',
            'internal_sallary_offer' => 'Penawaran Gaji Internal',
            'salary_offer' => 'Penawaran Gaji',
            'sallary_offer' => 'Penawaran Gaji',
            'approved' => 'Disetujui',
            'hired' => 'Hired',
            'rejected' => 'Ditolak',
            'void' => 'Void',
        ];

        return $labels[strtolower((string) $status)] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    protected function countAnsweredQuestions($answersJson)
    {
        $answers = json_decode($answersJson ?: '{}', true) ?: [];

        return count(array_filter($answers, function ($value) {
            return $value !== null && $value !== '';
        }));
    }

    protected function assessmentInProgressSummary($sessions)
    {
        foreach ($sessions as $session) {
            if ($session->status === 'in_progress') {
                $answered = $this->countAnsweredQuestions($session->answers_json);
                $total = (int) $session->question_count;

                return 'Sedang mengerjakan ' . $session->category_name . ' (' . $answered . '/' . $total . ' soal)';
            }

            if ($session->status === 'pending') {
                return 'Menunggu sesi ' . $session->category_name;
            }
        }

        return 'Assessment sedang berlangsung';
    }

    protected function buildAssessmentProgress($recruitmentId)
    {
        $attempt = DB::table('assessment_attempts')
            ->where('recruitment_id', $recruitmentId)
            ->orderByDesc('id')
            ->first();

        if (!$attempt) {
            return [
                'has_attempt' => false,
                'attempt_status' => null,
                'overall_progress' => 0,
                'total_answered' => 0,
                'total_questions' => 0,
                'sessions' => [],
                'summary' => 'Belum memulai assessment',
            ];
        }

        $sessions = DB::table('assessment_sessions')
            ->where('assessment_attempt_id', $attempt->id)
            ->orderBy('session_order')
            ->get();

        $sessionData = [];
        $totalAnswered = 0;
        $totalQuestions = 0;

        foreach ($sessions as $session) {
            $answered = $this->countAnsweredQuestions($session->answers_json);
            $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
            $questionCount = count($questions) ?: (int) $session->question_count;

            $totalAnswered += $answered;
            $totalQuestions += $questionCount;

            $sessionData[] = [
                'id' => (int) $session->id,
                'order' => (int) $session->session_order,
                'name' => $session->category_name,
                'status' => $session->status,
                'answered' => $answered,
                'total' => $questionCount,
                'progress_percent' => $questionCount > 0 ? round(($answered / $questionCount) * 100) : 0,
                'has_result' => !empty($session->result_json),
                'started_at' => $session->started_at,
                'completed_at' => $session->completed_at,
            ];
        }

        $summary = 'Assessment belum dimulai';
        if ($attempt->status === 'completed') {
            $summary = 'Assessment selesai';
        } elseif ($attempt->status === 'expired') {
            $summary = 'Assessment kedaluwarsa';
        } elseif ($attempt->status === 'in_progress') {
            $summary = $this->assessmentInProgressSummary($sessions);
        }

        return [
            'has_attempt' => true,
            'attempt_id' => (int) $attempt->id,
            'attempt_status' => $attempt->status,
            'started_at' => $attempt->started_at,
            'completed_at' => $attempt->completed_at,
            'overall_progress' => $totalQuestions > 0 ? round(($totalAnswered / $totalQuestions) * 100) : 0,
            'total_answered' => $totalAnswered,
            'total_questions' => $totalQuestions,
            'sessions' => $sessionData,
            'summary' => $summary,
        ];
    }

    protected function buildSessionResultSummary($session, array $result)
    {
        $engine = $result['engine'] ?? null;
        $items = [];
        $summaryText = 'Hasil assessment tersedia.';

        if ($engine === 'disc') {
            $lineLabels = [
                1 => 'Grafik 1 (Most)',
                2 => 'Grafik 2 (Least)',
                3 => 'Grafik 3 (Change)',
            ];

            foreach ($result['profiles'] ?? [] as $profile) {
                $line = (int) ($profile['line'] ?? 0);
                $scores = $profile['scores'] ?? [];
                $pattern = $profile['pattern'] ?? null;
                $patternLabel = '-';

                if (is_array($pattern)) {
                    $patternLabel = trim((string) ($pattern['pattern'] ?? $pattern['name'] ?? $pattern['id'] ?? ''));
                    if ($patternLabel === '' && isset($pattern['id'])) {
                        $patternLabel = 'Pattern ' . $pattern['id'];
                    }
                }

                $scoreText = sprintf(
                    'D:%s I:%s S:%s C:%s',
                    $scores['d'] ?? ($scores['D'] ?? '-'),
                    $scores['i'] ?? ($scores['I'] ?? '-'),
                    $scores['s'] ?? ($scores['S'] ?? '-'),
                    $scores['c'] ?? ($scores['C'] ?? '-')
                );

                $items[] = [
                    'label' => $lineLabels[$line] ?? ('Grafik ' . $line),
                    'value' => $patternLabel !== '-' ? $patternLabel . ' (' . $scoreText . ')' : $scoreText,
                ];
            }

            $answered = (int) ($result['answered'] ?? 0);
            $totalQuestions = (int) ($result['total_questions'] ?? 0);
            $summaryText = $totalQuestions > 0
                ? 'DISC selesai — ' . $answered . '/' . $totalQuestions . ' blok terjawab'
                : 'Hasil DISC tersedia';
        } elseif ($engine === 'papi_kostick') {
            foreach ($result['aspects'] ?? [] as $aspect) {
                $roles = $aspect['roles'] ?? [];
                if (empty($roles)) {
                    continue;
                }

                $roleSummaries = [];
                foreach (array_slice($roles, 0, 3) as $role) {
                    $roleSummaries[] = trim(
                        ($role['role_code'] ?? '-') . ' (' . ($role['score'] ?? 0) . '): ' . ($role['interpretation'] ?? '-')
                    );
                }

                $items[] = [
                    'label' => $aspect['aspect_name'] ?? 'Aspek',
                    'value' => implode('; ', $roleSummaries),
                ];
            }

            $answered = (int) ($result['answered'] ?? 0);
            $totalQuestions = (int) ($result['total_questions'] ?? 0);
            $summaryText = $totalQuestions > 0
                ? 'PAPI Kostick selesai — ' . $answered . '/' . $totalQuestions . ' item terjawab'
                : 'Hasil PAPI Kostick tersedia';
        } elseif ($engine === 'mixed') {
            $score = $result['score'] ?? null;
            $correct = (int) ($result['correct_answers'] ?? 0);
            $totalQuestions = (int) ($result['total_questions'] ?? 0);

            if ($score !== null) {
                $items[] = ['label' => 'Skor Gabungan', 'value' => $score . '/100'];
            }
            if (isset($result['choice_score'])) {
                $items[] = ['label' => 'Skor Pilihan Ganda', 'value' => $result['choice_score'] . '/100'];
            }
            if (isset($result['scale_score'])) {
                $items[] = ['label' => 'Skor Skala', 'value' => $result['scale_score'] . '/100'];
            }
            if ($totalQuestions > 0) {
                $items[] = ['label' => 'Jawaban Benar', 'value' => $correct . '/' . $totalQuestions];
            }

            $summaryText = $score !== null
                ? 'Skor gabungan ' . $score . '/100'
                : 'Hasil tes campuran tersedia';
        } elseif ($engine === 'scale_average') {
            $score = $result['score'] ?? 0;
            $averageValue = $result['average_value'] ?? null;
            $answered = (int) ($result['answered'] ?? 0);
            $totalQuestions = (int) ($result['total_questions'] ?? 0);

            $items[] = ['label' => 'Skor', 'value' => $score . '/100'];
            if ($averageValue !== null) {
                $items[] = ['label' => 'Rata-rata Nilai', 'value' => (string) $averageValue];
            }
            if ($totalQuestions > 0) {
                $items[] = ['label' => 'Terjawab', 'value' => $answered . '/' . $totalQuestions];
            }

            $summaryText = 'Skor ' . $score . '/100';
        } else {
            $score = $result['score'] ?? null;
            $correct = (int) ($result['correct_answers'] ?? 0);
            $totalQuestions = (int) ($result['total_questions'] ?? 0);
            $answered = (int) ($result['answered'] ?? 0);

            if ($score !== null) {
                $items[] = ['label' => 'Skor', 'value' => $score . '/100'];
            }
            if ($totalQuestions > 0 && $correct > 0) {
                $items[] = ['label' => 'Jawaban Benar', 'value' => $correct . '/' . $totalQuestions];
            } elseif ($totalQuestions > 0) {
                $items[] = ['label' => 'Terjawab', 'value' => $answered . '/' . $totalQuestions];
            }

            $summaryText = $score !== null
                ? $session->category_name . ': ' . $score . '/100'
                : 'Hasil tes tersedia';
        }

        return [
            'engine' => $engine,
            'summary_text' => $summaryText,
            'items' => $items,
            'scored_at' => $result['scored_at'] ?? $session->completed_at,
            'disc_detail' => $engine === 'disc' ? $this->buildDiscDetail($result) : null,
            'papi_detail' => $engine === 'papi_kostick' ? $this->buildPapiDetail($result) : null,
        ];
    }

    protected function buildDiscDetail(array $result)
    {
        $titles = [
            1 => 'Kepribadian dimuka umum',
            2 => 'Kepribadian saat mendapat tekanan',
            3 => 'Kepribadian asli yang tersembunyi',
        ];

        $profilesSource = $result['profiles'] ?? [];

        if (empty($profilesSource) && !empty($result['raw_scores'])) {
            $legacyScorer = app()->make(\App\Http\Controllers\api\EvaluasiKaryawanController::class);
            $profilesSource = [];

            foreach ([1, 2, 3] as $line) {
                $legacyResult = $legacyScorer->getPattern($result['raw_scores'], $line);
                $profilesSource[] = [
                    'line' => $line,
                    'scores' => (array) $legacyResult[0],
                    'pattern' => isset($legacyResult[1]) && is_object($legacyResult[1]) ? $legacyResult[1]->toArray() : null,
                ];
            }
        }

        $profiles = [];
        $primaryPattern = null;

        foreach ($profilesSource as $profile) {
            $line = (int) ($profile['line'] ?? 0);
            $pattern = is_array($profile['pattern'] ?? null) ? $profile['pattern'] : [];

            if (($line === 1 || empty($primaryPattern)) && !empty($pattern)) {
                $primaryPattern = $pattern;
            }

            $behaviourRaw = trim((string) ($pattern['behaviour'] ?? ''));
            $profiles[] = [
                'line' => $line,
                'title' => $titles[$line] ?? ('Grafik ' . $line),
                'pattern' => $pattern['pattern'] ?? 'Pattern tidak tersedia',
                'behaviours' => $behaviourRaw !== ''
                    ? array_values(array_filter(array_map('trim', explode(',', $behaviourRaw))))
                    : [],
            ];
        }

        usort($profiles, function ($a, $b) {
            return ($a['line'] ?? 0) <=> ($b['line'] ?? 0);
        });

        $description = trim((string) ($primaryPattern['description'] ?? ''));
        $jobsRaw = trim((string) ($primaryPattern['jobs'] ?? ''));

        return [
            'profiles' => $profiles,
            'description' => $description !== '' ? $description : null,
            'jobs' => $jobsRaw !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $jobsRaw))))
                : [],
        ];
    }

    protected function buildPapiDetail(array $result)
    {
        $rows = [];
        $no = 1;

        foreach ($result['aspects'] ?? [] as $aspect) {
            foreach ($aspect['roles'] ?? [] as $role) {
                $rows[] = [
                    'no' => $no++,
                    'role' => $role['role_description'] ?? '-',
                    'code' => $role['role_code'] ?? '-',
                    'score' => $role['score'] ?? 0,
                    'interpretation' => $role['interpretation'] ?? '-',
                ];
            }
        }

        return [
            'rows' => $rows,
        ];
    }

    protected function decodeMetaHistory($metaHistory)
    {
        if (is_array($metaHistory)) {
            return $metaHistory;
        }

        if (is_string($metaHistory) && $metaHistory !== '') {
            $decoded = json_decode($metaHistory, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function formatHrdInterviewSummary($candidate)
    {
        $hrd = $candidate->relationLoaded('hrdInterview')
            ? $candidate->hrdInterview
            : ($candidate->hrdInterview ?? null);

        if (!$hrd) {
            return null;
        }

        $catatan = $hrd->catatan_interview ?? $hrd->catatan ?? null;
        $catatanText = trim(strip_tags((string) $catatan));
        $statusResult = strtolower((string) ($hrd->status_result ?? ''));

        $hasReview = (int) ($candidate->is_input_review_hrd ?? 0) === 1
            || ($catatanText !== '' && $catatanText !== '<p></p>')
            || in_array($statusResult, ['passed', 'failed', 'evaluated', 'approved', 'rejected', 'recommended'], true);

        return [
            'id' => $hrd->id,
            'tgl_interview' => $hrd->tgl_interview,
            'jenis_interview' => $hrd->jenis_interview,
            'link_gmeet' => $hrd->link_gmeet,
            'ruangan_interview' => $hrd->ruangan_interview,
            'status_result' => $hrd->status_result,
            'catatan_interview' => $catatan,
            'updated_by' => $hrd->updated_by,
            'updated_at' => $hrd->updated_at,
            'has_review' => $hasReview,
        ];
    }

    protected function formatUserInterviewSummary($candidate)
    {
        $user = $candidate->relationLoaded('userInterview')
            ? $candidate->userInterview
            : ($candidate->userInterview ?? null);

        if (!$user) {
            return null;
        }

        $catatan = $user->catatan_interview ?? null;
        $catatanText = trim(strip_tags((string) $catatan));
        $statusResult = strtolower((string) ($user->status_result ?? ''));

        $hasDecision = !empty($candidate->approved_interview_user_at) || !empty($candidate->reject_interview_user_at);
        $hasReview = ($catatanText !== '' && $catatanText !== '<p></p>')
            || in_array($statusResult, ['lulus', 'gagal', 'passed', 'failed', 'approved', 'rejected', 'recommended'], true)
            || $hasDecision;

        return [
            'id' => $user->id,
            'tgl_interview' => $user->tgl_interview,
            'jenis_interview' => $user->jenis_interview,
            'link_gmeet' => $user->link_gmeet,
            'ruangan_interview' => $user->ruangan_interview,
            'status_result' => $user->status_result,
            'catatan_interview' => $catatan,
            'updated_by' => $user->updated_by,
            'updated_at' => $user->updated_at,
            'has_review' => $hasReview,
            'is_approved' => (int) ($candidate->is_approve_interview_user ?? 0) === 1,
            'is_rejected' => !empty($candidate->reject_interview_user_at),
            'approved_by' => $candidate->approved_interview_user,
            'approved_at' => $candidate->approved_interview_user_at,
            'rejected_by' => $candidate->reject_interview_user_by,
            'rejected_at' => $candidate->reject_interview_user_at,
        ];
    }

    protected function resolveMatchingReason($candidate): ?string
    {
        if (!empty($candidate->ai_matching_reason)) {
            return trim((string) $candidate->ai_matching_reason);
        }

        if (!empty($candidate->ai_matching_response)) {
            $parsed = json_decode($candidate->ai_matching_response, true);
            if (is_array($parsed) && !empty($parsed['reason'])) {
                return trim((string) $parsed['reason']);
            }
        }

        return null;
    }

    protected function formatCandidatePreviewItem($candidate, RecruitmentPictureService $pictureService)
    {
        $status = strtolower((string) $candidate->status);
        $isActive = (int) ($candidate->is_active ?? 1) === 1;
        $matchingScore = $candidate->nilai_kecocokan;
        if (($matchingScore === null || $matchingScore === '') && isset($candidate->matching_score)) {
            $matchingScore = $candidate->matching_score;
        }

        return [
            'id' => $candidate->id,
            'nama_lengkap' => $candidate->nama_lengkap,
            'email' => $candidate->email,
            'no_telepon' => $candidate->no_telepon,
            'picture' => $candidate->picture,
            'picture_url' => $pictureService->toDataUri($candidate->picture),
            'status' => $status,
            'is_active' => $isActive,
            'is_void' => !$isActive,
            'status_label' => $isActive ? $this->recruitmentStatusLabel($status) : 'Void',
            'nilai_kecocokan' => $matchingScore,
            'ai_matching_reason' => $this->resolveMatchingReason($candidate),
            'posisi_dilamar' => $candidate->posisi_dilamar,
            'applied_at' => $candidate->created_at,
            'updated_at' => $candidate->updated_at,
            'meta_history' => $this->decodeMetaHistory($candidate->meta_history),
            'assessment' => $this->buildAssessmentProgress($candidate->id),
            'hrd_interview' => $this->formatHrdInterviewSummary($candidate),
            'user_interview' => $this->formatUserInterviewSummary($candidate),
            'is_input_review_hrd' => (int) ($candidate->is_input_review_hrd ?? 0),
        ];
    }

    public function candidateSessionResult(Request $request)
    {
        $candidateId = $request->input('candidate_id');
        $sessionId = $request->input('session_id');

        if (!$candidateId || !$sessionId) {
            return response()->json(['message' => 'Parameter candidate_id dan session_id wajib diisi'], 400);
        }

        $candidate = NewRecruitment::find($candidateId);
        if (!$candidate) {
            return response()->json(['message' => 'Data kandidat tidak ditemukan'], 404);
        }

        $attempt = DB::table('assessment_attempts')
            ->where('recruitment_id', $candidateId)
            ->orderByDesc('id')
            ->first();

        if (!$attempt) {
            return response()->json(['message' => 'Assessment belum dimulai'], 404);
        }

        $session = DB::table('assessment_sessions')
            ->where('id', $sessionId)
            ->where('assessment_attempt_id', $attempt->id)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Sesi assessment tidak ditemukan'], 404);
        }

        if (empty($session->result_json)) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_id' => (int) $session->id,
                    'session_name' => $session->category_name,
                    'session_order' => (int) $session->session_order,
                    'status' => $session->status,
                    'has_result' => false,
                    'summary_text' => $session->status === 'completed'
                        ? 'Sesi selesai, namun hasil belum tersedia.'
                        : 'Sesi belum selesai, hasil belum tersedia.',
                    'items' => [],
                    'scored_at' => null,
                ],
            ], 200);
        }

        $result = json_decode($session->result_json, true) ?: [];
        $summary = $this->buildSessionResultSummary($session, $result);

        return response()->json([
            'status' => 'success',
            'data' => array_merge([
                'session_id' => (int) $session->id,
                'session_name' => $session->category_name,
                'session_order' => (int) $session->session_order,
                'status' => $session->status,
                'has_result' => true,
            ], $summary),
        ], 200);
    }
}
