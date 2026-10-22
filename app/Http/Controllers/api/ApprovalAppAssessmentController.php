<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;
use App\Services\ApprovalAppPenyesuaianDetailService;
use App\Services\PsychometricScoringService;
use Illuminate\Http\Request;

class ApprovalAppAssessmentController extends Controller
{
    use BuildsCandidateAssessmentPreview;

    public function sessionResult(int $id, int $sessionId, Request $request)
    {
        $request->merge([
            'candidate_id' => $id,
            'session_id' => $sessionId,
        ]);

        return $this->candidateSessionResult($request);
    }

    public function penyesuaianSessionResult(Request $request, int $sessionId, ApprovalAppPenyesuaianDetailService $detailService)
    {
        $token = (string) $request->query('token', '');
        $session = $detailService->findSessionForToken($token, $sessionId);

        if (!$session) {
            return response()->json(['message' => 'Sesi assessment tidak ditemukan'], 404);
        }

        if ($this->isHiddenAssessmentSession($session)) {
            return response()->json(['message' => 'Sesi assessment tidak ditampilkan'], 404);
        }

        $questions = is_array($session->questions_json) ? $session->questions_json : [];
        $answers = is_array($session->answers_json) ? $session->answers_json : [];
        $storedResult = is_array($session->result_json) ? $session->result_json : [];
        $categoryName = strtoupper(trim((string) $session->category_name));

        $result = $storedResult;
        if (!empty($questions) && !empty($answers)) {
            $employee = null;
            $requestId = optional($session->assessment)->request_id;
            if ($categoryName === '16PF' && $requestId) {
                $adjustmentRequest = \App\Models\SalaryAdjustmentRequest::find($requestId);
                if ($adjustmentRequest) {
                    $employee = app(\App\Services\SixteenPfScoringService::class)
                        ->employeeForRequest((int) $adjustmentRequest->employee_id);
                }
            }
            $result = app(PsychometricScoringService::class)->scoreSession($categoryName, $questions, $answers, $employee);
            if (!empty($storedResult['scored_at'])) {
                $result['scored_at'] = $storedResult['scored_at'];
            } elseif ($session->completed_at) {
                $result['scored_at'] = $session->completed_at;
            }
        }

        if (empty($result)) {
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
            ]);
        }

        $sessionObject = (object) [
            'category_name' => $session->category_name,
            'questions_json' => json_encode($questions),
            'answers_json' => json_encode($answers),
            'result_json' => json_encode($result),
            'completed_at' => $session->completed_at,
            'status' => $session->status,
        ];

        $summary = $this->buildSessionResultSummary($sessionObject, $result);

        return response()->json([
            'status' => 'success',
            'data' => array_merge([
                'session_id' => (int) $session->id,
                'session_name' => $session->category_name,
                'session_order' => (int) $session->session_order,
                'status' => $session->status,
                'has_result' => true,
            ], $summary),
        ]);
    }
}
