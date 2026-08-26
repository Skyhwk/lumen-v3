<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ScaleScoringService;

class MoveInternalAssessmentAnswersToSessions extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('assessment_internal_sessions')) {
            return;
        }

        if (!Schema::hasColumn('assessment_internal_sessions', 'answers_json')) {
            Schema::table('assessment_internal_sessions', function (Blueprint $table) {
                $table->longText('answers_json')->nullable()->after('questions_json');
            });
        }

        if (!Schema::hasColumn('assessment_internal_sessions', 'result_json')) {
            Schema::table('assessment_internal_sessions', function (Blueprint $table) {
                $table->longText('result_json')->nullable()->after('answers_json');
            });
        }

        $hasLegacyAnswers = Schema::hasTable('assessment_internal_answers');

        DB::table('assessment_internal_sessions')->orderBy('id')->chunk(100, function ($sessions) use ($hasLegacyAnswers) {
            foreach ($sessions as $session) {
                $answers = json_decode($session->answers_json ?: '{}', true) ?: [];
                if ($hasLegacyAnswers) {
                    $legacyAnswers = DB::table('assessment_internal_answers')
                        ->where('assessment_internal_session_id', $session->id)
                        ->orderBy('id')
                        ->get(['question_id', 'answer_json'])
                        ->mapWithKeys(function ($answer) {
                            $decoded = json_decode($answer->answer_json, true);
                            return [(string) $answer->question_id => json_last_error() === JSON_ERROR_NONE ? $decoded : $answer->answer_json];
                        })->all();
                    $answers = array_replace($answers, $legacyAnswers);
                }

                $updates = [
                    'answers_json' => json_encode($answers ?: new \stdClass()),
                ];
                if (in_array($session->status, ['completed', 'expired'], true)) {
                    $result = $this->scoreSession($session, $answers);
                    $result['status'] = $session->status;
                    $result['scored_at'] = $session->completed_at ?: $session->updated_at ?: date('Y-m-d H:i:s');
                    $updates['result_json'] = json_encode($result);
                }

                DB::table('assessment_internal_sessions')->where('id', $session->id)->update($updates);
            }
        });

        if ($hasLegacyAnswers) {
            Schema::dropIfExists('assessment_internal_answers');
        }
    }

    public function down()
    {
        // Non-destructive: jawaban yang sudah disatukan ke sesi tidak dipisahkan kembali.
    }

    private function scoreSession($session, array $answers)
    {
        $questions = json_decode($session->questions_json ?: '[]', true) ?: [];
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
}
