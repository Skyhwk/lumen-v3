<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\StepTracer;

class AbsFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'ABS'; }
    public function minArgs(): int { return 1; }
    public function maxArgs(): int { return 1; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        $result = abs($args[0]);
        $tracer->addStep('ABS(' . $args[0] . ')', $result, 'Nilai absolut');
        return $result;
    }
}
