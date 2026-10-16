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

        return Datatables::of($data)
            ->filterColumn('ga_asset_locations.created_at', function ($query, $keyword) {
                $this->filterCreatedAt($query, $keyword);
            })
            ->make(true);
    }

    public function branches(Request $request)
    {
        $locationQuery = GaAssetLocation::query()
            ->leftJoin('master_cabang', 'master_cabang.id', '=', 'ga_asset_locations.branch_id')
            ->where('ga_asset_locations.is_active', true);
        $this->applySearchFilters($locationQuery, $request, [
            'ga_asset_locations.code',
            'ga_asset_locations.name',
            'master_cabang.nama_cabang',
            'ga_asset_locations.created_by',
        ], 'ga_asset_locations.created_at');

        $roomQuery = GaAssetRoom::query()
            ->join('ga_asset_locations', 'ga_asset_locations.id', '=', 'ga_asset_rooms.location_id')
            ->leftJoin('master_cabang', 'master_cabang.id', '=', 'ga_asset_locations.branch_id')
            ->where('ga_asset_rooms.is_active', true);
        $this->applySearchFilters($roomQuery, $request, [
            'ga_asset_rooms.code',
            'ga_asset_rooms.name',
            'ga_asset_locations.name',
            'master_cabang.nama_cabang',
            'ga_asset_rooms.created_by',
        ], 'ga_asset_rooms.created_at');

        $locationCounts = (clone $locationQuery)
            ->selectRaw('ga_asset_locations.branch_id as branch_id, COUNT(*) as total')
            ->groupBy('ga_asset_locations.branch_id')
            ->pluck('total', 'branch_id');

        $roomCounts = (clone $roomQuery)
            ->selectRaw('ga_asset_locations.branch_id as branch_id, COUNT(*) as total')
            ->groupBy('ga_asset_locations.branch_id')
            ->pluck('total', 'branch_id');

        $rows = MasterCabang::where('is_active', true)
            ->orderBy('nama_cabang')
            ->get(['id', 'kode_cabang', 'nama_cabang'])
            ->map(function ($row) use ($locationCounts, $roomCounts) {
                return [
                    'id' => $row->id,
                    'kode_cabang' => $row->kode_cabang,
                    'nama_cabang' => $row->nama_cabang,
                    'location_count' => (int) ($locationCounts[$row->id] ?? 0),
                    'room_count' => (int) ($roomCounts[$row->id] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'totals' => [
                'location_count' => (int) (clone $locationQuery)->count('ga_asset_locations.id'),
                'room_count' => (int) (clone $roomQuery)->count('ga_asset_rooms.id'),
            ],
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

    private function applySearchFilters($query, Request $request, array $textColumns, string $createdAtColumn): void
    {
        $columns = $request->input('columns', []);
        if (!is_array($columns)) {
            $columns = [];
        }

        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $name = (string) ($column['name'] ?? '');
            $value = trim((string) ($column['search']['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            if ($name === $createdAtColumn) {
                $this->filterCreatedAt($query, $value, $createdAtColumn);
                continue;
            }
            if (in_array($name, $textColumns, true)) {
                $query->where($name, 'like', '%' . $value . '%');
            }
        }

        $global = trim((string) $request->input('search.value', ''));
        if ($global === '') {
            return;
        }

        $like = '%' . $global . '%';
        $query->where(function ($inner) use ($textColumns, $createdAtColumn, $like) {
            foreach ($textColumns as $column) {
                $inner->orWhere($column, 'like', $like);
            }
            $inner->orWhere($createdAtColumn, 'like', $like);
        });
    }

    private function filterCreatedAt($query, string $keyword, string $column = 'ga_asset_locations.created_at'): void
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $keyword)) {
            $query->where($column, 'like', '%' . $keyword . '%');
            return;
        }

        $months = [
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
        ];
        $lower = mb_strtolower($keyword);
        $month = null;
        foreach ($months as $name => $number) {
            if (mb_strpos($lower, $name) !== false) {
                $month = $number;
                $lower = trim(str_replace($name, ' ', $lower));
                break;
            }
        }

        $year = null;
        if (preg_match('/\b(19|20)\d{2}\b/', $keyword, $yearMatch)) {
            $year = (int) $yearMatch[0];
        }

        $day = null;
        if (preg_match('/\b([1-9]|[12]\d|3[01])\b/', $lower, $dayMatch)) {
            $day = (int) $dayMatch[1];
        }

        if (!$month && !$year && !$day) {
            $query->where($column, 'like', '%' . $keyword . '%');
            return;
        }

        if ($year) {
            $query->whereYear($column, $year);
        }
        if ($month) {
            $query->whereMonth($column, $month);
        }
        if ($day) {
            $query->whereDay($column, $day);
        }
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
