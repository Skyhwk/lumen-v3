<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAssetLocation;
use App\Models\GaAssetRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class GaAssetRoomController extends Controller
{
    public function index(Request $request)
    {
        $data = GaAssetRoom::query()
            ->leftJoin('ga_asset_locations', 'ga_asset_locations.id', '=', 'ga_asset_rooms.location_id')
            ->leftJoin('master_cabang', 'master_cabang.id', '=', 'ga_asset_locations.branch_id')
            ->where('ga_asset_rooms.is_active', true)
            ->select([
                'ga_asset_rooms.id',
                'ga_asset_rooms.location_id',
                'ga_asset_rooms.code',
                'ga_asset_rooms.name',
                'ga_asset_rooms.is_active',
                'ga_asset_rooms.created_by',
                'ga_asset_rooms.updated_by',
                'ga_asset_rooms.created_at',
                'ga_asset_rooms.updated_at',
                'ga_asset_locations.name as location_name',
                'ga_asset_locations.branch_id',
                'master_cabang.nama_cabang',
            ]);

        if ($request->filled('location_id')) {
            $data->where('ga_asset_rooms.location_id', (int) $request->location_id);
        }

        return Datatables::of($data)->make(true);
    }

    public function options(Request $request)
    {
        $query = GaAssetRoom::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->location_id);
        }

        $rows = $query->get(['id', 'location_id', 'code', 'name']);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->payload($request);
        $validator = Validator::make($payload, [
            'location_id' => ['required', 'integer'],
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        if (!$this->locationExists($payload['location_id'])) {
            return response()->json(['message' => 'Lokasi tidak ditemukan.'], 422);
        }

        $existing = $this->findByName($payload['location_id'], $payload['name']);
        if ($existing && $existing->is_active) {
            return response()->json(['message' => 'Nama ruang sudah dipakai di lokasi ini.'], 422);
        }

        if ($existing) {
            $existing->update([
                'code' => $payload['code'],
                'is_active' => true,
                'updated_by' => $this->karyawan,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ruang aset berhasil diaktifkan kembali.',
                'data' => $existing,
            ]);
        }

        $room = GaAssetRoom::create([
            'location_id' => $payload['location_id'],
            'code' => $payload['code'],
            'name' => $payload['name'],
            'is_active' => true,
            'created_by' => $this->karyawan,
            'updated_by' => $this->karyawan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ruang aset berhasil dibuat.',
            'data' => $room,
        ], 201);
    }

    public function update(Request $request)
    {
        $payload = $this->payload($request);
        $validator = Validator::make($payload + ['id' => $request->id], [
            'id' => ['required', 'integer'],
            'location_id' => ['required', 'integer'],
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:200'],
        ]);

        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $room = GaAssetRoom::where('id', (int) $request->id)->where('is_active', true)->first();
        if (!$room) {
            return response()->json(['message' => 'Ruang tidak ditemukan.'], 404);
        }

        if (!$this->locationExists($payload['location_id'])) {
            return response()->json(['message' => 'Lokasi tidak ditemukan.'], 422);
        }

        $duplicate = $this->findByName($payload['location_id'], $payload['name'], $room->id);
        if ($duplicate) {
            return response()->json(['message' => 'Nama ruang sudah dipakai di lokasi ini.'], 422);
        }

        $room->update([
            'location_id' => $payload['location_id'],
            'code' => $payload['code'],
            'name' => $payload['name'],
            'updated_by' => $this->karyawan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ruang aset berhasil diupdate.',
            'data' => $room,
        ]);
    }

    public function delete(Request $request)
    {
        $room = GaAssetRoom::where('id', (int) $request->id)->where('is_active', true)->first();
        if (!$room) {
            return response()->json(['message' => 'Ruang tidak ditemukan.'], 404);
        }

        $room->update([
            'is_active' => false,
            'updated_by' => $this->karyawan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ruang aset berhasil dinonaktifkan.',
        ]);
    }

    private function payload(Request $request): array
    {
        $code = trim((string) $request->input('code'));

        return [
            'location_id' => (int) $request->input('location_id'),
            'code' => $code === '' ? null : strtoupper($code),
            'name' => trim((string) $request->input('name')),
        ];
    }

    private function locationExists(int $locationId): bool
    {
        return GaAssetLocation::where('id', $locationId)->where('is_active', true)->exists();
    }

    private function findByName(int $locationId, string $name, int $ignoreId = 0)
    {
        return GaAssetRoom::where('location_id', $locationId)
            ->where('name', $name)
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
