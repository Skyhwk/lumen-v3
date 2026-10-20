<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\GaAsset\GaAssetDepreciationService;
use App\Services\GaAsset\GaAssetExcelReconcileService;
use Illuminate\Http\Request;

class GaAssetReportController extends Controller
{
    public function index(Request $request, GaAssetDepreciationService $service)
    {
        $year = (int) $request->input('year', date('Y'));
        if ($year < 1990 || $year > ((int) date('Y') + 1)) {
            return response()->json(['message' => 'Tahun laporan tidak valid.'], 422);
        }

        return response()->json($service->report($year));
    }

    public function quantity(Request $request, GaAssetDepreciationService $service)
    {
        if ($request->boolean('with_branches')) {
            return response()->json($service->quantityMatrixByBranch());
        }

        $branchId = (int) $request->input('branch_id', 0);

        return response()->json($service->quantityMatrix($branchId > 0 ? $branchId : null));
    }

    public function totalAkhir(Request $request, GaAssetDepreciationService $service)
    {
        $year = (int) $request->input('year', date('Y'));
        if ($year < 1990 || $year > ((int) date('Y') + 1)) {
            return response()->json(['message' => 'Tahun laporan tidak valid.'], 422);
        }

        return response()->json($service->totalAssetAkhirReport($year));
    }

    public function reconcile(Request $request, GaAssetExcelReconcileService $reconcile, GaAssetDepreciationService $depreciation)
    {
        $path = trim((string) $request->input('path', ''));
        if ($path === '' || !is_file($path)) {
            return response()->json(['message' => 'Path file Excel tidak valid atau tidak dapat diakses server.'], 422);
        }

        $year = (int) $request->input('year', date('Y'));
        $category = $request->input('category');
        $sub = $request->input('sub_category');
        $tolerance = (string) $request->input('tolerance', '1');

        try {
            $result = $reconcile->compareYear(
                $path,
                $depreciation,
                $year,
                is_string($category) && $category !== '' ? $category : null,
                is_string($sub) && $sub !== '' ? $sub : null,
                $tolerance
            );
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function rebuild(Request $request, GaAssetDepreciationService $service)
    {
        @set_time_limit(0);

        $year = (int) $request->input('year', date('Y'));
        $current = (int) date('Y');
        if ($year < 1990 || $year > $current + 1) {
            return response()->json(['message' => 'Tahun laporan tidak valid.'], 422);
        }

        $throughYear = max($year, $current);

        try {
            $stats = $service->rebuild($throughYear);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Gagal menghitung depresiasi: ' . $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => sprintf(
                'Depresiasi dihitung sampai tahun %d. %d aset tersimpan, %d dilewati karena harga, tanggal beli, atau umur kosong.',
                $stats['through_year'],
                $stats['assets_snapshotted'],
                $stats['assets_skipped']
            ),
            'stats' => $stats,
            'report' => $service->report($year),
        ]);
    }
}
