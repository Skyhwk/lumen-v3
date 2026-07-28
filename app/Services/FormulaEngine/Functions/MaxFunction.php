<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\StepTracer;

class MaxFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'MAX'; }
    public function minArgs(): int { return 2; }
    public function maxArgs(): int { return 99; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        $result = max($args);
        $tracer->addStep('MAX(' . implode(', ', $args) . ')', $result, 'Nilai maksimum');
        return $result;
    }
}
