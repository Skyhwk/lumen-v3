<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\StepTracer;

class PowerFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'POWER'; }
    public function minArgs(): int { return 2; }
    public function maxArgs(): int { return 2; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        $result = pow($args[0], $args[1]);
        $tracer->addStep($args[0] . ' ^ ' . $args[1], $result, 'Pangkat');
        return $result;
    }
}
