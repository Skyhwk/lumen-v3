<?php

namespace App\Jobs;

use App\Models\OrderBackdatePackage;
use App\Models\OrderBackdateStep;
use App\Services\OrderBackdate\OrderBackdateDocumentRegenerator;
use Carbon\Carbon;

class OrderBackdateRegenerateStepJob extends Job
{
    public $timeout = 600;

    /** @var int */
    public $packageId;

    /** @var string */
    public $stepKey;

    public function __construct(int $packageId, string $stepKey)
    {
        $this->packageId = $packageId;
        $this->stepKey = $stepKey;
    }

    public function handle(OrderBackdateDocumentRegenerator $regenerator): void
    {
        $package = OrderBackdatePackage::find($this->packageId);
        if (!$package || !$package->is_active) {
            return;
        }

        $step = OrderBackdateStep::where('package_id', $package->id)
            ->where('step_key', $this->stepKey)
            ->first();

        if (!$step) {
            return;
        }

        try {
            $regenerator->regenerate($package, $this->stepKey);
            $step->document_status = 'done';
            $step->last_error = null;
        } catch (\Throwable $e) {
            $step->document_status = 'failed';
            $step->last_error = $e->getMessage();
        }

        $step->updated_at = Carbon::now();
        $step->save();
    }
}
