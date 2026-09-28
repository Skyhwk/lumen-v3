<?php

namespace App\Http\Controllers\Greatday;

use App\Services\Greatday\LeaveBalanceService;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function summary(Request $request)
    {
        try {
            $employee = $this->karyawan;
            if (!$employee) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $data = app(LeaveBalanceService::class)->summary($employee);

            return response()->json([
                'data' => $data,
                'message' => 'Leave balance retrieved successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Failed to load leave balance: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function usage(Request $request)
    {
        try {
            $employee = $this->karyawan;
            if (!$employee) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(50, max(1, (int) $request->input('per_page', 20)));

            $result = app(LeaveBalanceService::class)->usageLedger($employee, $page, $perPage);

            return response()->json([
                'data' => $result['items'],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more' => $result['has_more'],
                ],
                'message' => 'Leave usage retrieved successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Failed to load leave usage: ' . $th->getMessage(),
            ], 500);
        }
    }
}
