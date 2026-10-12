<?php

namespace App\Http\Controllers\external;

use App\Services\Payroll\PayrollTransferCompletionService;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

/**
 * Callback dari worker Python setelah proses transfer payroll selesai.
 *
 * POST /api/payroll-transfer/complete
 * Header: X-Payroll-Callback-Token: {PAYROLL_DISBURSEMENT_CALLBACK_TOKEN}
 * Body JSON atau form:
 *   payroll_header_id (required)
 *   payroll_ids (optional array) — subset baris; kosong = semua di header
 */
class PayrollTransferCallbackController extends BaseController
{
    public function complete(Request $request)
    {
        $expected = (string) config('payroll_disbursement.callback_token');
        $provided = (string) (
            $request->header('X-Payroll-Callback-Token')
            ?? $request->input('token')
            ?? $request->input('callback_token')
        );

        if ($expected === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Token callback tidak valid.'], 403);
        }

        $payrollHeaderId = (int) $request->input('payroll_header_id');
        if ($payrollHeaderId <= 0) {
            return response()->json(['message' => 'payroll_header_id wajib diisi.'], 422);
        }

        $payrollIds = $request->input('payroll_ids');
        if ($payrollIds !== null && !is_array($payrollIds)) {
            $payrollIds = [$payrollIds];
        }
        if (is_array($payrollIds)) {
            $payrollIds = array_values(array_filter(array_map('intval', $payrollIds)));
        }

        try {
            $result = app(PayrollTransferCompletionService::class)->complete(
                $payrollHeaderId,
                $payrollIds
            );

            return response()->json([
                'message' => 'Payroll completion processed.',
                'data' => $result,
            ], 200);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }
}
