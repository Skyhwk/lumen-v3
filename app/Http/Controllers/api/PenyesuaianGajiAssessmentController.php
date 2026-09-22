<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SalaryAdjustmentRequest;
use App\Services\SalaryAdjustmentAssessmentService;
use Illuminate\Http\Request;

class PenyesuaianGajiAssessmentController extends Controller
{
    public function getCategories(Request $request, BankSoalController $bankSoal)
    {
        return $bankSoal->categories($request);
    }

    public function generateLink(Request $request, SalaryAdjustmentAssessmentService $service)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        try {
            $result = $service->generateLink($record, $request->all(), $this->karyawan);

            return response()->json([
                'success' => true,
                'message' => 'Link assessment berhasil digenerate',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
