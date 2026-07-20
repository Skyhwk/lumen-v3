<?php

namespace App\Services\FormulaEngine;

class FormulaException extends \Exception
{
    public string $errorCode;
    public ?int $position;

    public function __construct(string $message, string $errorCode = 'FORMULA_ERROR', ?int $position = null)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->position = $position;
    }
}
