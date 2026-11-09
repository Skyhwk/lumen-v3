<?php

namespace App\Services\OrderBackdate;

use Exception;

class OrderBackdateNotEligibleException extends Exception
{
    /** @var array<string, mixed> */
    public $eligibility;

    /**
     * @param  array<string, mixed>  $eligibility
     */
    public function __construct(string $message, array $eligibility = [])
    {
        parent::__construct($message, 422);
        $this->eligibility = $eligibility;
    }
}
