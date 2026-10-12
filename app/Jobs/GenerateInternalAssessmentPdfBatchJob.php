<?php

namespace App\Jobs;

use App\Services\InternalAssessmentParticipantPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateInternalAssessmentPdfBatchJob extends Job
{
    protected int $assessmentId;

    public function __construct(int $assessmentId)
    {
        $this->assessmentId = $assessmentId;
    }

    public function handle(): void
    {
        $service = app(InternalAssessmentParticipantPdfService::class);

        try {
            $payload = $service->processAssessment($this->assessmentId, null, false, false);

            DB::table('job_task')->insert([
                'job' => 'GenerateInternalAssessmentPdfBatchJob',
                'status' => 'success',
                'no_document' => 'assessment_internal_' . $this->assessmentId,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            Log::info('Internal assessment PDF batch generated', [
                'assessment_id' => $this->assessmentId,
                'count' => count($payload['generated'] ?? []),
                'failed' => count($payload['failed'] ?? []),
            ]);
        } catch (\Throwable $e) {
            DB::table('job_task')->insert([
                'job' => 'GenerateInternalAssessmentPdfBatchJob',
                'status' => 'failed',
                'no_document' => 'assessment_internal_' . $this->assessmentId,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            Log::error('Internal assessment PDF batch failed', [
                'assessment_id' => $this->assessmentId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
