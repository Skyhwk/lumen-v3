<?php

namespace App\Services;

use App\Models\SalaryAdjustmentAssessment;

class SalaryAdjustmentAssessmentReportService
{
    /** @var PsychometricScoringService */
    private $scoring;

    /** @var AssessmentResultPresenter */
    private $presenter;

    public function __construct(
        PsychometricScoringService $scoring = null,
        AssessmentResultPresenter $presenter = null
    ) {
        $this->scoring = $scoring ?: new PsychometricScoringService();
        $this->presenter = $presenter ?: new AssessmentResultPresenter();
    }

    public function buildAssessmentReport(?SalaryAdjustmentAssessment $assessment): ?array
    {
        if (!$assessment) {
            return null;
        }

        $assessment->loadMissing('sessions');
        $sessions = [];

        foreach ($assessment->sessions->sortBy('session_order') as $session) {
            $questions = is_array($session->questions_json) ? $session->questions_json : [];
            $answers = is_array($session->answers_json) ? $session->answers_json : [];
            $storedResult = is_array($session->result_json) ? $session->result_json : [];
            $categoryName = strtoupper(trim((string) $session->category_name));

            $result = $storedResult;
            if (!empty($questions) && !empty($answers)) {
                $result = $this->scoring->scoreSession($categoryName, $questions, $answers);
                if (!empty($storedResult['scored_at'])) {
                    $result['scored_at'] = $storedResult['scored_at'];
                } elseif ($session->completed_at) {
                    $result['scored_at'] = $session->completed_at;
                }
            }

            $sessionObject = (object) [
                'category_name' => $session->category_name,
                'questions_json' => json_encode($questions),
                'answers_json' => json_encode($answers),
                'result_json' => json_encode($result),
                'completed_at' => $session->completed_at,
                'status' => $session->status,
            ];

            $formatted = $this->presenter->presentSession($sessionObject, $result);

            $sessions[] = array_merge([
                'id' => (int) $session->id,
                'session_order' => (int) $session->session_order,
                'category_name' => $session->category_name,
                'status' => $session->status,
                'question_count' => (int) $session->question_count,
                'completed_at' => $session->completed_at,
                'score' => isset($result['score']) ? (float) $result['score'] : null,
                'has_result' => !empty($result),
            ], $formatted);
        }

        return [
            'id' => (int) $assessment->id,
            'total_score' => $assessment->total_score !== null ? (float) $assessment->total_score : null,
            'attempt_status' => $assessment->attempt_status,
            'completed_at' => $assessment->completed_at,
            'sessions' => $sessions,
        ];
    }
}
