<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\SalaryAdjustmentApprovalService;
use Illuminate\Http\Request;

class PenyesuaianGajiApprovalPublicController extends Controller
{
    public function overview(Request $request, SalaryAdjustmentApprovalService $service)
    {
        $token = (string) $request->input('token', '');
        $state = $service->overview($token);

        if (($state['result'] ?? null) === 'invalid') {
            return response()->json($state, 404);
        }

        if (($state['result'] ?? null) === 'unavailable') {
            return response()->json($state, 403);
        }

        return response()->json($state);
    }

    public function decide(Request $request, SalaryAdjustmentApprovalService $service)
    {
        $token = (string) $request->input('token', '');
        $decision = (string) ($request->input('decision') ?? $request->query('decision') ?? '');

        try {
            $result = $service->decide(
                $token,
                $decision,
                (string) ($request->input('reject_reason') ?? '')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (($result['result'] ?? null) === 'invalid') {
            return response()->json($result, 404);
        }

        if (($result['result'] ?? null) === 'unavailable') {
            return response()->json($result, 403);
        }

        return response()->json($result);
    }
}
