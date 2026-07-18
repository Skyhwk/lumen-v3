<?php

namespace App\Services\FormulaEngine;

class StepTracer
{
    private array $steps = [];

    public function addStep(string $expression, float $result, string $description = ''): void
    {
        $this->steps[] = [
            'expression' => $expression,
            'result' => $result,
            'description' => $description,
        ];
    }

    public function getSteps(): array
    {
        return $this->steps;
    }
}
