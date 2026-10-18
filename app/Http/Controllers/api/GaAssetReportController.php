<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\GaAsset\GaAssetDepreciationService;
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

    public function quantity(GaAssetDepreciationService $service)
    {
        return response()->json($service->quantityMatrix());
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
