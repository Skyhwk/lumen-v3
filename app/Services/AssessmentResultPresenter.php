<?php

namespace App\Services;

use App\Http\Controllers\api\Concerns\BuildsCandidateAssessmentPreview;

class AssessmentResultPresenter
{
    use BuildsCandidateAssessmentPreview;

    public function presentSession($session, array $result): array
    {
        return $this->buildSessionResultSummary($session, $result);
    }
}
