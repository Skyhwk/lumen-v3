<?php

namespace App\Services\FormulaEngine;

class StepTracer
{
    private array $steps = [];

    public function addStep(string $expression, float $result, string $description = '', ?string $resultFormatted = null): void
    {
        $step = [
            'expression' => $expression,
            'result' => $result,
            'description' => $description,
        ];

        if ($resultFormatted !== null) {
            $step['result_formatted'] = $resultFormatted;
        }

        $this->steps[] = $step;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }
}
