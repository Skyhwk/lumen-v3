<?php

namespace App\Services\FormulaEngine\Contracts;

use App\Services\FormulaEngine\StepTracer;

interface FunctionHandlerInterface
{
    public function name(): string;

    public function minArgs(): int;

    public function maxArgs(): int;

    public function evaluate(array $args, StepTracer $tracer): float;
}
