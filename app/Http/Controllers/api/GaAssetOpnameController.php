<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\GaAssetOpname;
use App\Models\GaAssetOpnameLine;
use App\Models\GaAssetRoom;
use App\Services\GaAsset\GaAssetOpnameExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GaAssetOpnameController extends Controller
{
    public function index()
    {
        $rows = GaAssetOpname::query()
            ->with('cabang:id,nama_cabang')
            ->withCount('lines')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function open(Request $request)
    {
        $branchId = (int) $request->input('branch_id');
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        if ($branchId < 1 || $year < 2000 || $month < 1 || $month > 12) {
            return response()->json(['message' => 'Cabang, bulan, dan tahun wajib valid.'], 422);
        }

        $existing = GaAssetOpname::query()
            ->where('branch_id', $branchId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
        if ($existing) {
            return response()->json([
                'message' => 'Periode opname ini sudah ada.',
                'data' => $existing,
            ]);
        }

        $opname = GaAssetOpname::create([
            'branch_id' => $branchId,
            'period_year' => $year,
            'period_month' => $month,
            'status' => 'open',
            'opened_by' => trim((string) $this->karyawan) ?: 'GA Asset System',
        ]);

        return response()->json([
            'message' => 'Periode opname dibuka.',
            'data' => $opname,
        ]);
    }

    public function rooms(Request $request)
    {
        $opname = $this->findOpname((int) $request->input('opname_id'));
        if (!$opname) {
            return response()->json(['message' => 'Periode opname tidak ditemukan.'], 404);
        }

        $rooms = GaAssetRoom::query()
            ->with('location:id,name,branch_id')
            ->where('is_active', 1)
            ->whereHas('location', function ($query) use ($opname) {
                $query->where('branch_id', $opname->branch_id);
            })
            ->orderBy('name')
            ->get(['id', 'location_id', 'code', 'name']);

        return response()->json(['data' => $rooms]);
    }

    public function scan(Request $request)
    {
        $opname = $this->findOpname((int) $request->input('opname_id'));
        if (!$opname) {
            return response()->json(['message' => 'Periode opname tidak ditemukan.'], 404);
        }
        if ($opname->status !== 'open') {
            return response()->json(['message' => 'Periode opname sudah ditutup.'], 422);
        }

        $roomId = (int) $request->input('room_id');
        try {
            $code = (new \App\Services\AssetQrValidationService())->opnameCode((string) $request->input('cs_code'));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if ($roomId < 1 || $code === '') {
            return response()->json(['message' => 'Ruang dan No CS wajib diisi.'], 422);
        }

        $roomOk = GaAssetRoom::query()
            ->where('id', $roomId)
            ->where('is_active', 1)
            ->whereHas('location', function ($query) use ($opname) {
                $query->where('branch_id', $opname->branch_id);
            })
            ->exists();
        if (!$roomOk) {
            return response()->json(['message' => 'Ruang tidak termasuk cabang opname ini.'], 422);
        }

        $asset = GaAsset::query()->where('is_active', 1)->where('cs_code', $code)->first();
        if (!$asset) {
            $result = 'unknown';
            $assetId = null;
        } elseif ((int) $asset->room_id === $roomId) {
            $result = 'found';
            $assetId = $asset->id;
        } else {
            $result = 'wrong_room';
            $assetId = $asset->id;
        }

        $line = GaAssetOpnameLine::query()->updateOrCreate(
            ['opname_id' => $opname->id, 'scanned_code' => $code],
            [
                'room_id' => $roomId,
                'asset_id' => $assetId,
                'result' => $result,
                'scanned_by' => trim((string) $this->karyawan) ?: 'GA Asset System',
                'scanned_at' => Carbon::now(),
            ]
        );

        $labels = [
            'found' => 'Cocok dengan ruang ini.',
            'wrong_room' => 'Barcode dikenal, tetapi ruang sistem berbeda.',
            'unknown' => 'Barcode tidak ada di daftar aset.',
        ];

        return response()->json([
            'message' => $labels[$result],
            'data' => [
                'result' => $result,
                'cs_code' => $code,
                'asset_name' => $asset->name ?? null,
                'line_id' => $line->id,
            ],
        ]);
    }

    public function lines(Request $request)
    {
        $opname = $this->findOpname((int) $request->input('opname_id'));
        if (!$opname) {
            return response()->json(['message' => 'Periode opname tidak ditemukan.'], 404);
        }

        $lines = GaAssetOpnameLine::query()
            ->with(['room:id,name', 'asset:id,name,cs_code,room_id'])
            ->where('opname_id', $opname->id)
            ->orderByDesc('scanned_at')
            ->limit(500)
            ->get();

        return response()->json(['data' => $lines]);
    }

    public function recap(Request $request)
    {
        $opname = $this->findOpname((int) $request->input('opname_id'));
        if (!$opname) {
            return response()->json(['message' => 'Periode opname tidak ditemukan.'], 404);
        }

        $counts = GaAssetOpnameLine::query()
            ->where('opname_id', $opname->id)
            ->selectRaw('result, COUNT(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result');

        $scannedIds = GaAssetOpnameLine::query()
            ->where('opname_id', $opname->id)
            ->whereNotNull('asset_id')
            ->pluck('asset_id');

        $missing = GaAsset::query()
            ->where('is_active', 1)
            ->where('branch_id', $opname->branch_id)
            ->whereNotNull('cs_code')
            ->where('cs_code', '!=', '')
            ->when($scannedIds->isNotEmpty(), function ($query) use ($scannedIds) {
                $query->whereNotIn('id', $scannedIds->all());
            })
            ->orderBy('cs_code')
            ->limit(300)
            ->get(['id', 'cs_code', 'name', 'room_id']);

        $missingCount = GaAsset::query()
            ->where('is_active', 1)
            ->where('branch_id', $opname->branch_id)
            ->whereNotNull('cs_code')
            ->where('cs_code', '!=', '')
            ->when($scannedIds->isNotEmpty(), function ($query) use ($scannedIds) {
                $query->whereNotIn('id', $scannedIds->all());
            })
            ->count();

        return response()->json([
            'data' => [
                'found' => (int) ($counts['found'] ?? 0),
                'wrong_room' => (int) ($counts['wrong_room'] ?? 0),
                'unknown' => (int) ($counts['unknown'] ?? 0),
                'not_scanned' => $missingCount,
                'not_scanned_sample' => $missing,
            ],
        ]);
    }

    public function exportExcel(Request $request, GaAssetOpnameExportService $exportService)
    {
        $opname = $this->findOpname((int) $request->input('opname_id'));
        if (!$opname) {
            return response()->json(['message' => 'Periode opname tidak ditemukan.'], 404);
        }

        $spreadsheet = $exportService->exportOpnamePeriod($opname);
        $filename = sprintf(
            'opname_%s_%02d_%d.xlsx',
            $opname->branch_id,
            $opname->period_month,
            $opname->period_year
        );
        $exportService->stream($spreadsheet, $filename);
    }

    public function exportSoMatrix(Request $request, GaAssetOpnameExportService $exportService)
    {
        $branchId = (int) $request->input('branch_id');
        $year = (int) $request->input('year', date('Y'));
        if ($branchId < 1 || $year < 2000) {
            return response()->json(['message' => 'Cabang dan tahun wajib valid.'], 422);
        }

        $spreadsheet = $exportService->exportSoMatrix($branchId, $year);
        $filename = sprintf('so_matrix_%d_%d.xlsx', $branchId, $year);
        $exportService->stream($spreadsheet, $filename);
    }

    public function close(Request $request)
    {
        $opname = $this->findOpname((int) $request->input('opname_id'));
        if (!$opname) {
            return response()->json(['message' => 'Periode opname tidak ditemukan.'], 404);
        }
        if ($opname->status === 'closed') {
            return response()->json(['message' => 'Periode sudah ditutup.', 'data' => $opname]);
        }

        $opname->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Periode opname ditutup.', 'data' => $opname]);
    }

    private function findOpname(int $id): ?GaAssetOpname
    {
        if ($id < 1) {
            return null;
        }

        return GaAssetOpname::query()->find($id);
    }
}
