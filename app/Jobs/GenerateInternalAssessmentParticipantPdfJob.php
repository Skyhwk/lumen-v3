<?php

namespace App\Jobs;

use App\Services\InternalAssessmentParticipantPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateInternalAssessmentParticipantPdfJob extends Job
{
    protected int $attemptId;

    public function __construct(int $attemptId)
    {
        $this->attemptId = $attemptId;
    }

    public function handle(): void
    {
        try {
            $payload = app(InternalAssessmentParticipantPdfService::class)
                ->generateForAttempt($this->attemptId);

            DB::table('job_task')->insert([
                'job' => 'GenerateInternalAssessmentParticipantPdfJob',
                'status' => 'success',
                'no_document' => 'attempt_' . $this->attemptId,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            Log::info('Internal assessment participant PDF generated', [
                'attempt_id' => $this->attemptId,
                'link' => $payload['link'] ?? null,
            ]);
        } catch (\Throwable $e) {
            DB::table('job_task')->insert([
                'job' => 'GenerateInternalAssessmentParticipantPdfJob',
                'status' => 'failed',
                'no_document' => 'attempt_' . $this->attemptId,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            Log::error('Internal assessment participant PDF failed', [
                'attempt_id' => $this->attemptId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
