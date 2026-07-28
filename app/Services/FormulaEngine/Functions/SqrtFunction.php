<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\FormulaException;
use App\Services\FormulaEngine\StepTracer;

class SqrtFunction implements FunctionHandlerInterface
{
    public function name(): string { return 'SQRT'; }
    public function minArgs(): int { return 1; }
    public function maxArgs(): int { return 1; }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        if ($args[0] < 0) {
            throw new FormulaException('Akar kuadrat dari bilangan negatif tidak valid.', 'INVALID_SQRT');
        }

        $result = sqrt($args[0]);
        $tracer->addStep('SQRT(' . $args[0] . ')', $result, 'Akar kuadrat');
        return $result;
    }
}
