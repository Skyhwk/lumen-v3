<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PsychometricScoringService
{
    public function scoreSession(string $categoryName, array $questions, array $answers): array
    {
        $normalized = strtoupper(trim($categoryName));

        if ($normalized === 'DISC') {
            return $this->scoreDisc($questions, $answers);
        }

        if (in_array($normalized, ['KOSTICK PAPI', 'PAPI KOSTICK'], true)) {
            return $this->scorePapi($questions, $answers);
        }

        return $this->scoreQuestionBank($questions, $answers);
    }

    public function scoreQuestionBank(array $questions, array $answers): array
    {
        $scaleQuestions = collect($questions)->filter(function ($question) {
            return ($question['type'] ?? '') === 'scale'
                || ($question['scoring_type'] ?? '') === 'scale_average';
        });
        $choiceQuestions = collect($questions)->reject(function ($question) {
            return ($question['type'] ?? '') === 'scale'
                || ($question['scoring_type'] ?? '') === 'scale_average';
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

    public function scorePapi(array $questions, array $answers): array
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
            ->select(
                'papi_roles.id',
                'papi_roles.code',
                'papi_roles.role',
                'papi_aspects.id as aspect_id',
                'papi_aspects.aspect as aspect_name'
            )
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

    public function scoreDisc(array $questions, array $answers): array
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
}
