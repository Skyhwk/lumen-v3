<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\GaAssetMovement;
use App\Models\GaAssetRoom;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GaAssetMovementController extends Controller
{
    public function index()
    {
        try {
            $movements = GaAssetMovement::with([
                'asset',
                'fromBranch',
                'toBranch',
                'fromLocation',
                'toLocation',
                'fromRoom',
                'toRoom',
            ])->orderByDesc('moved_at')->orderByDesc('id')->get();

            return response()->json([
                'success' => true,
                'data' => $movements,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function searchAssetByNoCs(Request $request)
    {
        $noCs = trim((string) $request->input('no_cs'));

        if ($noCs === '') {
            return response()->json([
                'success' => false,
                'message' => 'No CS is required',
            ], 400);
        }

        try {
            $asset = GaAsset::with(['cabang', 'location', 'room'])->where('cs_code', $noCs)->first();

            if (!$asset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $asset,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getRuangByBranchId(Request $request)
    {
        $branchId = $request->input('branch_id');

        if ($branchId === null || $branchId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Branch ID is required',
            ], 400);
        }

        try {
            $ruang = GaAssetRoom::with('location')
                ->where('is_active', true)
                ->whereHas('location', function ($query) use ($branchId) {
                    $query->where('branch_id', (int) $branchId)
                        ->where('is_active', true);
                })
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ruang,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function transferAsset(Request $request)
    {
        $noCs = trim((string) $request->input('no_cs'));
        $roomId = $request->input('room_id');

        if ($noCs === '' || $roomId === null || $roomId === '') {
            return response()->json([
                'success' => false,
                'message' => 'No CS and Room ID are required',
            ], 400);
        }

        try {
            $asset = GaAsset::where('cs_code', $noCs)->first();
            if (!$asset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset not found',
                ], 404);
            }

            $newRoom = GaAssetRoom::with('location')
                ->where('id', (int) $roomId)
                ->where('is_active', true)
                ->first();
            if (!$newRoom || !$newRoom->location || !$newRoom->location->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruang not found',
                ], 404);
            }

            $fromBranchId = $asset->branch_id;
            $fromLocationId = $asset->location_id;
            $fromRoomId = $asset->room_id;
            $toBranchId = $newRoom->location->branch_id;
            $now = Carbon::now();

            DB::transaction(function () use ($asset, $newRoom, $fromBranchId, $fromLocationId, $fromRoomId, $toBranchId, $now, $request) {
                $asset->branch_id = $toBranchId;
                $asset->location_id = $newRoom->location_id;
                $asset->room_id = $newRoom->id;
                $asset->updated_by = $this->karyawan;
                $asset->save();

                GaAssetMovement::create([
                    'ga_asset_id' => $asset->id,
                    'movement_kind' => (int) $fromBranchId === (int) $toBranchId
                        ? GaAssetMovement::KIND_ROOM
                        : GaAssetMovement::KIND_BRANCH,
                    'from_branch_id' => $fromBranchId,
                    'to_branch_id' => $toBranchId,
                    'from_location_id' => $fromLocationId,
                    'to_location_id' => $newRoom->location_id,
                    'from_room_id' => $fromRoomId,
                    'to_room_id' => $newRoom->id,
                    'note' => trim((string) $request->input('note')) ?: null,
                    'created_by' => $this->karyawan,
                    'moved_at' => $now,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Asset transferred successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
