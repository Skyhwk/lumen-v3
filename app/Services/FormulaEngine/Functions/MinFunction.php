<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\StepTracer;

class MinFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'MIN'; }
    public function minArgs(): int { return 2; }
    public function maxArgs(): int { return 99; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        $result = min($args);
        $tracer->addStep('MIN(' . implode(', ', $args) . ')', $result, 'Nilai minimum');
        return $result;
    }
}
