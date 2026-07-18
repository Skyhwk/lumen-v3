<?php

namespace App\Services\FormulaEngine\Functions;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;
use App\Services\FormulaEngine\StepTracer;

class RoundFunction implements FunctionHandlerInterface
{
    public function name(): string
    {
        return 'ROUND';
    }

    public function minArgs(): int
    {
        return 1;
    }

    public function maxArgs(): int
    {
        return 2;
    }

    public function evaluate(array $args, StepTracer $tracer): float
    {
        $value = $args[0];
        $precision = isset($args[1]) ? (int) $args[1] : 0;
        $result = round($value, $precision);
        $tracer->addStep('ROUND(' . $this->format($value) . ', ' . $precision . ')', $result, 'Pembulatan');
        return $result;
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
