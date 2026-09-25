<?php

namespace App\Jobs;

use App\Models\Jadwal;
use App\Services\SamplerTrackingService;
use Illuminate\Support\Facades\Log;

class SyncSamplerTrackingScheduleJob extends Job
{
    public $tries = 3;
    public $backoff = 30;

    protected $noQuotation;
    protected $before;
    protected $isCreation;

    public function __construct($noQuotation, array $before, $isCreation = false)
    {
        $this->noQuotation = $noQuotation;
        $this->before = $before;
        $this->isCreation = (bool) $isCreation;
    }

    public function handle(SamplerTrackingService $tracking)
    {
        $before = Jadwal::hydrate($this->before);

        try {
            if ($this->isCreation) {
                $tracking->syncScheduleCreation($before, $this->noQuotation);
            } else {
                $tracking->syncScheduleEdit($before, $this->noQuotation);
            }
        } catch (\Throwable $exception) {
            Log::channel('sampling')->error('SyncSamplerTrackingScheduleJob gagal.', [
                'no_quotation' => $this->noQuotation,
                'jenis_perubahan' => $this->isCreation ? 'creation' : 'edit',
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ]);

            throw $exception;
        }
    }
}
