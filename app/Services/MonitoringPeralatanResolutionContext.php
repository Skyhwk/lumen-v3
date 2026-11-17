<?php

namespace App\Services;

class MonitoringPeralatanResolutionContext
{
    public string $asOfDate;

    /** @var array<int, list<object>> */
    public array $eventsByPeralatan = [];

    /** @var array<string, true> */
    public array $ioKodes = [];

    /** @var array<string, string> */
    public array $ioKodeOwners = [];

    public function __construct(string $asOfDate)
    {
        $this->asOfDate = $asOfDate;
    }
}