<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAssetLocation;
use App\Models\GaAssetRoom;
use App\Models\MasterCabang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class GaAssetLocationController extends Controller
{
    public function index(Request $request)
    {
        $data = GaAssetLocation::query()
            ->leftJoin('master_cabang', 'master_cabang.id', '=', 'ga_asset_locations.branch_id')
            ->where('ga_asset_locations.is_active', true)
            ->select([
                'ga_asset_locations.id',
                'ga_asset_locations.branch_id',
                'ga_asset_locations.code',
                'ga_asset_locations.name',
                'ga_asset_locations.is_active',
                'ga_asset_locations.created_by',
                'ga_asset_locations.updated_by',
                'ga_asset_locations.created_at',
                'ga_asset_locations.updated_at',
                'master_cabang.nama_cabang',
            ]);

        if ($request->filled('branch_id')) {
            $data->where('ga_asset_locations.branch_id', (int) $request->branch_id);
        }

        return Datatables::of($data)->make(true);
    }

    public function branches()
    {
        $rows = MasterCabang::where('is_active', true)
            ->orderBy('nama_cabang')
            ->get(['id', 'kode_cabang', 'nama_cabang']);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function options(Request $request)
    {
        $query = GaAssetLocation::query()
            ->leftJoin('master_cabang', 'master_cabang.id', '=', 'ga_asset_locations.branch_id')
            ->where('ga_asset_locations.is_active', true)
            ->orderBy('ga_asset_locations.name');

        if ($request->filled('branch_id')) {
            $query->where('ga_asset_locations.branch_id', (int) $request->branch_id);
        }

        $rows = $query->get([
            'ga_asset_locations.id',
            'ga_asset_locations.branch_id',
            'ga_asset_locations.code',
            'ga_asset_locations.name',
            'master_cabang.nama_cabang',
        ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->payload($request);
        $validator = Validator::make($payload, [
            'branch_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        if (!$this->branchExists($payload['branch_id'])) {
            return response()->json(['message' => 'Cabang tidak ditemukan.'], 422);
        }

        $existing = $this->findByCode($payload['branch_id'], $payload['code']);
        if ($existing && $existing->is_active) {
            return response()->json(['message' => 'Kode lokasi sudah dipakai di cabang ini.'], 422);
        }

        if ($existing) {
            $existing->update([
                'name' => $payload['name'],
                'is_active' => true,
                'updated_by' => $this->karyawan,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lokasi aset berhasil diaktifkan kembali.',
                'data' => $existing,
            ]);
        }

        $location = GaAssetLocation::create([
            'branch_id' => $payload['branch_id'],
            'code' => $payload['code'],
            'name' => $payload['name'],
            'is_active' => true,
            'created_by' => $this->karyawan,
            'updated_by' => $this->karyawan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lokasi aset berhasil dibuat.',
            'data' => $location,
        ], 201);
    }

    public function update(Request $request)
    {
        $payload = $this->payload($request);
        $validator = Validator::make($payload + ['id' => $request->id], [
            'id' => ['required', 'integer'],
            'branch_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $location = GaAssetLocation::where('id', (int) $request->id)->where('is_active', true)->first();
        if (!$location) {
            return response()->json(['message' => 'Lokasi tidak ditemukan.'], 404);
        }

        if (!$this->branchExists($payload['branch_id'])) {
            return response()->json(['message' => 'Cabang tidak ditemukan.'], 422);
        }

        $duplicate = $this->findByCode($payload['branch_id'], $payload['code'], $location->id);
        if ($duplicate) {
            return response()->json(['message' => 'Kode lokasi sudah dipakai di cabang ini.'], 422);
        }

        $location->update([
            'branch_id' => $payload['branch_id'],
            'code' => $payload['code'],
            'name' => $payload['name'],
            'updated_by' => $this->karyawan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lokasi aset berhasil diupdate.',
            'data' => $location,
        ]);
    }

    public function delete(Request $request)
    {
        $location = GaAssetLocation::where('id', (int) $request->id)->where('is_active', true)->first();
        if (!$location) {
            return response()->json(['message' => 'Lokasi tidak ditemukan.'], 404);
        }

        $hasRoom = GaAssetRoom::where('location_id', $location->id)->where('is_active', true)->exists();
        if ($hasRoom) {
            return response()->json(['message' => 'Lokasi masih punya ruang aktif dan tidak dapat dinonaktifkan.'], 422);
        }

        $location->update([
            'is_active' => false,
            'updated_by' => $this->karyawan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lokasi aset berhasil dinonaktifkan.',
        ]);
    }

    private function payload(Request $request): array
    {
        return [
            'branch_id' => (int) $request->input('branch_id'),
            'code' => strtoupper(trim((string) $request->input('code'))),
            'name' => trim((string) $request->input('name')),
        ];
    }

    private function branchExists(int $branchId): bool
    {
        return MasterCabang::where('id', $branchId)->where('is_active', true)->exists();
    }

    private function findByCode(int $branchId, string $code, int $ignoreId = 0)
    {
        return GaAssetLocation::where('branch_id', $branchId)
            ->where('code', $code)
            ->when($ignoreId, function ($query) use ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            })
            ->first();
    }

    private function invalid($validator)
    {
        return response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }
}
