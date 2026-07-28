<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\FormulaException;
use App\Services\FormulaEngine\StepTracer;

class LogFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'LOG'; }
    public function minArgs(): int { return 1; }
    public function maxArgs(): int { return 1; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        if ($args[0] <= 0) {
            throw new FormulaException('LOG hanya valid untuk bilangan positif.', 'INVALID_LOG');
        }

        $result = log10($args[0]);
        $tracer->addStep('LOG(' . $args[0] . ')', $result, 'Logaritma');
        return $result;
    }
}
