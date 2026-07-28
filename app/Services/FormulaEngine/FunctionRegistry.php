<?php

namespace App\Services\FormulaEngine;

use App\Services\FormulaEngine\Contracts\FunctionHandlerInterface;

class FunctionRegistry
{
    /** @var array<string, FunctionHandlerInterface> */
    private array $handlers = [];

    public function register(FunctionHandlerInterface $handler): void
    {
        $this->handlers[strtoupper($handler->name())] = $handler;
    }

    public function has(string $name): bool
    {
        return isset($this->handlers[strtoupper($name)]);
    }

    public function get(string $name): FunctionHandlerInterface
    {
        $key = strtoupper($name);
        if (!isset($this->handlers[$key])) {
            throw new FormulaException('Function "' . $name . '" tidak dikenal.', 'UNKNOWN_FUNCTION');
        }

        return $this->handlers[$key];
    }

    public function allowedNames(): array
    {
        return array_keys($this->handlers);
    }
}
