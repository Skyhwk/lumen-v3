<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\StepTracer;

class CeilFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'CEIL'; }
    public function minArgs(): int { return 1; }
    public function maxArgs(): int { return 1; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        $result = ceil($args[0]);
        $tracer->addStep('CEIL(' . $args[0] . ')', $result, 'Pembulatan ke atas');
        return $result;
    }
}
