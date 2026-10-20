<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\GaAssetMovement;
use App\Models\GaAssetRoom;
use App\Services\GaAsset\GaAssetMovementService;
use Illuminate\Http\Request;

class GaAssetMovementController extends Controller
{
    public function index()
    {
        $rows = GaAssetMovement::query()
            ->with([
                'asset:id,cs_code,name',
                'fromBranch:id,nama_cabang',
                'toBranch:id,nama_cabang',
                'fromLocation:id,name',
                'toLocation:id,name',
                'fromRoom:id,name',
                'toRoom:id,name',
            ])
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function searchAssetByNoCs(Request $request)
    {
        $code = trim((string) $request->input('no_cs'));
        if ($code === '') {
            return response()->json(['message' => 'No CS wajib diisi.'], 422);
        }

        $asset = GaAsset::query()
            ->where('is_active', 1)
            ->where(function ($query) use ($code) {
                $query->where('cs_code', $code)->orWhere('asset_code', $code);
            })
            ->with([
                'cabang:id,nama_cabang',
                'location:id,name',
                'room:id,name',
            ])
            ->first();

        if (!$asset) {
            return response()->json(['message' => 'Aset dengan No CS tersebut tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $asset]);
    }

    public function getRuangByBranchId(Request $request)
    {
        $branchId = (int) $request->input('branch_id');
        if ($branchId < 1) {
            return response()->json(['message' => 'Cabang wajib dipilih.'], 422);
        }

        $rooms = GaAssetRoom::query()
            ->with('location:id,name,branch_id')
            ->where('is_active', 1)
            ->whereHas('location', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->where('is_active', 1);
            })
            ->orderBy('name')
            ->get(['id', 'location_id', 'code', 'name']);

        return response()->json(['data' => $rooms]);
    }

    public function transferAsset(Request $request, GaAssetMovementService $service)
    {
        $code = trim((string) $request->input('no_cs'));
        $roomId = (int) $request->input('room_id');
        if ($code === '' || $roomId < 1) {
            return response()->json(['message' => 'No CS dan ruang tujuan wajib diisi.'], 422);
        }

        $asset = GaAsset::query()->where('is_active', 1)->where('cs_code', $code)->first();
        if (!$asset) {
            return response()->json(['message' => 'Aset tidak ditemukan.'], 404);
        }

        try {
            $actor = trim((string) $this->karyawan) ?: 'GA Asset System';
            $service->transfer($asset, $roomId, $request->input('note'), $actor);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Gagal memindahkan aset: ' . $exception->getMessage()], 500);
        }

        return response()->json(['message' => 'Aset berhasil dipindahkan.']);
    }
}
