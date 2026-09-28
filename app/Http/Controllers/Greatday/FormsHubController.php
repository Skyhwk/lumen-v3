<?php

namespace App\Http\Controllers\Greatday;

use App\Services\Greatday\FormsHubService;
use Illuminate\Http\Request;

class FormsHubController extends Controller
{
    public function overview(Request $request)
    {
        return $this->stats($request);
    }

    /** Statistik + jumlah badge tab (tanpa list item). */
    public function stats(Request $request)
    {
        try {
            $employee = $this->karyawan;
            if (!$employee) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $data = app(FormsHubService::class)->stats($employee);

            return response()->json([
                'data' => $data,
                'message' => 'Forms stats retrieved successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Failed to load forms stats: ' . $th->getMessage(),
            ], 500);
        }
    }

    /** List per tab dengan paginasi (lazy load dari client). */
    public function list(Request $request)
    {
        try {
            $employee = $this->karyawan;
            if (!$employee) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $tab = (string) $request->input('tab', '');
            if (!in_array($tab, ['submission', 'approval', 'history'], true)) {
                return response()->json(['message' => 'Invalid tab'], 422);
            }

            $page = max(1, (int) $request->input('page', 1));
            $perPage = min(20, max(1, (int) $request->input('per_page', 5)));

            $result = app(FormsHubService::class)->listTab($employee, $tab, $page, $perPage);

            return response()->json([
                'data' => $result['items'],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more' => $result['has_more'],
                ],
                'message' => 'Forms list retrieved successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Failed to load forms list: ' . $th->getMessage(),
            ], 500);
        }
    }
}
