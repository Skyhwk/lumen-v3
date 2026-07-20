<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\StepTracer;

class FloorFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'FLOOR'; }
    public function minArgs(): int { return 1; }
    public function maxArgs(): int { return 1; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        $result = floor($args[0]);
        $tracer->addStep('FLOOR(' . $args[0] . ')', $result, 'Pembulatan ke bawah');
        return $result;
    }
}
