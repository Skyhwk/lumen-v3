<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SalaryAdjustmentAssessmentEngine;
use App\Services\SalaryAdjustmentAssessmentService;
use Illuminate\Http\Request;

class PenyesuaianGajiAssessmentPublicController extends Controller
{
    public function overview(Request $request, SalaryAdjustmentAssessmentService $service, SalaryAdjustmentAssessmentEngine $engine)
    {
        $assessment = $service->findByToken((string) $request->input('token', ''));
        if (!$assessment) {
            return response()->json(['message' => 'Link assessment tidak valid.'], 404);
        }

        return response()->json($engine->overview($assessment));
    }

    public function start(Request $request, SalaryAdjustmentAssessmentService $service, SalaryAdjustmentAssessmentEngine $engine)
    {
        $assessment = $service->findByToken((string) $request->input('token', ''));
        if (!$assessment) {
            return response()->json(['message' => 'Link assessment tidak valid.'], 404);
        }

        $result = $engine->start($assessment);
        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['code'] ?? 400);
        }

        return response()->json($result['state'] ?? $result);
    }

    public function state(Request $request, SalaryAdjustmentAssessmentService $service, SalaryAdjustmentAssessmentEngine $engine)
    {
        $assessment = $service->findByToken((string) $request->input('token', ''));
        if (!$assessment) {
            return response()->json(['message' => 'Link assessment tidak valid.'], 404);
        }

        return response()->json($engine->state($assessment));
    }

    public function answer(Request $request, SalaryAdjustmentAssessmentService $service, SalaryAdjustmentAssessmentEngine $engine)
    {
        $assessment = $service->findByToken((string) $request->input('token', ''));
        if (!$assessment) {
            return response()->json(['message' => 'Link assessment tidak valid.'], 404);
        }

        $result = $engine->answer(
            $assessment,
            (string) $request->input('question_id'),
            $request->input('answer')
        );

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['code'] ?? 400);
        }

        return response()->json($result['state'] ?? $result, $result['code'] ?? 200);
    }
}
