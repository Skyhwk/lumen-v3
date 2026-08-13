<?php

namespace App\Services;

class ScaleScoringService
{
    /**
     * Hitung skor persentase (0-100) dari soal bertipe scale.
     *
     * Rumus: (jumlah nilai terpilih / jumlah soal scale / nilai maksimum per soal) * 100
     * Setara: (total nilai / total nilai maksimum yang mungkin) * 100
     */
    public static function scoreQuestions(array $questions, array $answers): array
    {
        $scaleQuestions = array_values(array_filter($questions, function ($question) {
            return ($question['type'] ?? '') === 'scale'
                || ($question['scoring_type'] ?? '') === 'scale_average';
        }));

        $questionCount = count($scaleQuestions);
        if ($questionCount === 0) {
            return [
                'engine' => 'scale_average',
                'answered' => 0,
                'total_questions' => 0,
                'total_value' => 0,
                'max_possible' => 0,
                'average_value' => 0,
                'score' => 0,
                'details' => [],
            ];
        }

        $totalValue = 0;
        $totalMax = 0;
        $answered = 0;
        $details = [];

        foreach ($scaleQuestions as $question) {
            $bounds = self::resolveBounds($question);
            $answer = $answers[$question['id']] ?? null;
            $numericValue = 0;

            if ($answer !== null && $answer !== '') {
                $extracted = self::extractScaleValue($answer, $question, $bounds);
                if ($extracted !== null) {
                    $answered++;
                    $numericValue = max($bounds['min'], min($bounds['max'], $extracted));
                }
            }

            $totalValue += $numericValue;
            $totalMax += $bounds['max'];

            $questionPercentage = $bounds['max'] > 0
                ? round(($numericValue / $bounds['max']) * 100, 2)
                : 0;

            $details[] = [
                'question_id' => $question['id'],
                'value' => $numericValue,
                'min' => $bounds['min'],
                'max' => $bounds['max'],
                'question_percentage' => $questionPercentage,
            ];
        }

        $averageValue = $questionCount > 0 ? round($totalValue / $questionCount, 2) : 0;
        $score = $totalMax > 0 ? round(($totalValue / $totalMax) * 100, 2) : 0;

        return [
            'engine' => 'scale_average',
            'answered' => $answered,
            'total_questions' => $questionCount,
            'total_value' => round($totalValue, 2),
            'max_possible' => round($totalMax, 2),
            'average_value' => $averageValue,
            'score' => min(100, max(0, $score)),
            'details' => $details,
        ];
    }

    public static function buildScaleOptions($scale): array
    {
        $options = collect(json_decode($scale->options ?? '[]', true) ?: [])
            ->map(function ($option, $optionKey) {
                $value = is_numeric($option['value'] ?? null) ? (float) $option['value'] : null;
                if ($value === null) {
                    return null;
                }

                $label = trim((string) ($option['label'] ?? ''));

                return [
                    'id' => 'scale-' . self::normalizeScaleId($value),
                    'text' => $label !== '' ? "{$label} ({$value})" : (string) $value,
                    'label' => $label,
                    'value' => $value,
                    'is_correct' => false,
                ];
            })
            ->filter()
            ->sortByDesc('value')
            ->values()
            ->all();

        return $options;
    }

    public static function resolveBounds(array $question): array
    {
        if (isset($question['scale_min'], $question['scale_max'])) {
            return [
                'min' => (float) $question['scale_min'],
                'max' => (float) $question['scale_max'],
            ];
        }

        $values = collect($question['options'] ?? [])
            ->map(function ($option) {
                if (isset($option['value']) && is_numeric($option['value'])) {
                    return (float) $option['value'];
                }

                return self::extractScaleValue($option['id'] ?? null, $question);
            })
            ->filter(fn ($value) => $value !== null)
            ->values();

        if ($values->isEmpty()) {
            return ['min' => 0, 'max' => 0];
        }

        return [
            'min' => (float) $values->min(),
            'max' => (float) $values->max(),
        ];
    }

    public static function extractScaleValue($answer, array $question = [], array $bounds = null): ?float
    {
        if (is_array($answer)) {
            $answer = reset($answer);
        }

        if ($answer === null || $answer === '') {
            return null;
        }

        if (is_numeric($answer)) {
            return (float) $answer;
        }

        $answerString = (string) $answer;

        if (preg_match('/^scale-(.+)$/', $answerString, $matches)) {
            return is_numeric($matches[1]) ? (float) $matches[1] : null;
        }

        foreach ($question['options'] ?? [] as $option) {
            if ((string) ($option['id'] ?? '') === $answerString && isset($option['value']) && is_numeric($option['value'])) {
                return (float) $option['value'];
            }
        }

        return null;
    }

    private static function normalizeScaleId($value): string
    {
        if (is_numeric($value) && floor((float) $value) != (float) $value) {
            return str_replace('.', '_', (string) $value);
        }

        return (string) $value;
    }
}
