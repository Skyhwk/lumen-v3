<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ItSupportAssetController extends Controller
{
    private function ready()
    {
        if (!Schema::hasTable('it_support_assets')) {
            abort(422, 'Tabel Asset IT belum tersedia. Jalankan migration Asset IT terlebih dahulu.');
        }
    }

    public function index(Request $request)
    {
        $this->ready();
        $assets = DB::table('it_support_assets as a')
            ->leftJoin('it_asset_locations as l', 'l.id', '=', 'a.location_id')
            ->leftJoin('it_asset_locations as f', 'f.id', '=', 'l.parent_id')
            ->leftJoin('it_asset_locations as b', 'b.id', '=', 'f.parent_id')
            ->leftJoin('master_karyawan as k', 'k.id', '=', 'a.assigned_karyawan_id')
            ->select('a.*', 'l.name as location_name', DB::raw("CONCAT_WS(' / ', b.name, f.name, l.name) as location_path"), 'k.nama_lengkap as assigned_name')
            ->orderByDesc('a.updated_at');
        if ($request->filled('location_id')) {
            $assets->where('a.location_id', (int) $request->location_id);
        }
        return Datatables::of($assets)->editColumn('specifications', function ($row) {
            return json_decode($row->specifications ?: '{}', true) ?: [];
        })->editColumn('map_position', function ($row) {
            return json_decode($row->map_position ?: '{}', true) ?: [];
        })->make(true);
    }

    public function dashboard()
    {
        $this->ready();
        $assets = DB::table('it_support_assets')->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');
        $locations = DB::table('it_asset_locations')->where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $locationIndex = $locations->keyBy('id');
        $locations->each(function ($location) use ($locationIndex) {
            $parts = [$location->name];
            $parentId = $location->parent_id;
            while ($parentId && $locationIndex->has($parentId)) {
                $parent = $locationIndex->get($parentId);
                array_unshift($parts, $parent->name);
                $parentId = $parent->parent_id;
            }
            $location->path = implode(' / ', $parts);
        });
        return response()->json([
            'summary' => ['total' => array_sum($assets->all()), 'active' => (int) ($assets['active'] ?? 0), 'issue' => (int) ($assets['issue'] ?? 0), 'maintenance' => (int) ($assets['maintenance'] ?? 0)],
            'locations' => $locations,
            'employees' => MasterKaryawan::where('is_active', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'id_department']),
        ]);
    }

    public function map(Request $request)
    {
        $this->ready();
        $locationId = $request->location_id;
        $location = $locationId ? DB::table('it_asset_locations')->find($locationId) : null;
        $assets = DB::table('it_support_assets as a')->leftJoin('master_karyawan as k', 'k.id', '=', 'a.assigned_karyawan_id')
            ->where('a.location_id', $locationId)->select('a.*', 'k.nama_lengkap as assigned_name')->get()
            ->map(function ($asset) {
                $asset->map_position = json_decode($asset->map_position ?: '{}', true) ?: [];
                $asset->specifications = json_decode($asset->specifications ?: '{}', true) ?: [];
                return $asset;
            });
        return response()->json(['location' => $location, 'assets' => $assets]);
    }

    public function save(Request $request)
    {
        $this->ready();
        $validator = Validator::make($request->all(), ['asset_type' => 'required|string|max:50', 'name' => 'required|string|max:191', 'status' => 'required|in:active,issue,maintenance,retired']);
        if ($validator->fails()) return response()->json(['message' => $validator->errors()->first()], 422);
        if (strtoupper(trim((string) $request->asset_type)) === 'PC') {
            $specifications = is_array($request->specifications) ? $request->specifications : [];
            foreach (['processor' => 'Processor', 'motherboard' => 'Motherboard', 'mouse' => 'Mouse', 'keyboard' => 'Keyboard'] as $key => $label) {
                if (!isset($specifications[$key]) || trim((string) $specifications[$key]) === '') {
                    return response()->json(['message' => $label . ' wajib diisi untuk perangkat PC'], 422);
                }
            }
            foreach (['ram' => 'RAM', 'storage' => 'Storage', 'monitors' => 'Monitor'] as $key => $label) {
                if (empty($specifications[$key]) || !is_array($specifications[$key])) {
                    return response()->json(['message' => $label . ' minimal harus memiliki satu item untuk perangkat PC'], 422);
                }
            }
        }
        if ($request->location_id && DB::table('it_asset_locations')->where('id', $request->location_id)->where('type', 'room')->doesntExist()) {
            return response()->json(['message' => 'Pilih lokasi sampai level ruangan'], 422);
        }
        $asset = $request->id ? DB::table('it_support_assets')->where('id', $request->id)->first() : null;
        if ($request->id && !$asset) return response()->json(['message' => 'Aset tidak ditemukan'], 404);
        $payload = [
            'asset_type' => $request->asset_type, 'name' => $request->name, 'brand' => $request->brand,
            'model' => $request->model, 'serial_number' => $request->serial_number, 'location_id' => $request->location_id ?: null,
            'department_id' => $request->department_id ?: null, 'assigned_karyawan_id' => $request->assigned_karyawan_id ?: null,
            'status' => $request->status, 'specifications' => json_encode($request->specifications ?: [], JSON_UNESCAPED_UNICODE),
            'notes' => $request->notes, 'updated_by' => $this->user_id ?? null, 'updated_at' => Carbon::now(),
        ];
        if ($asset) DB::table('it_support_assets')->where('id', $asset->id)->update($payload);
        else { $payload['asset_code'] = 'IT-' . Carbon::now()->format('ymdHis') . '-' . random_int(10, 99); $payload['created_by'] = $this->user_id ?? null; $payload['created_at'] = Carbon::now(); DB::table('it_support_assets')->insert($payload); }
        return response()->json(['message' => 'Aset IT berhasil disimpan']);
    }

    public function saveLocation(Request $request)
    {
        $this->ready();
        $validator = Validator::make($request->all(), ['room_name' => 'required|string|max:191', 'building_name' => 'required|string|max:191', 'floor_name' => 'required|string|max:191']);
        if ($validator->fails()) return response()->json(['message' => $validator->errors()->first()], 422);
        $now = Carbon::now();
        $building = DB::table('it_asset_locations')->where('type', 'building')->whereRaw('LOWER(name) = ?', [strtolower(trim($request->building_name))])->first();
        if (!$building) {
            $buildingId = DB::table('it_asset_locations')->insertGetId(['name' => trim($request->building_name), 'type' => 'building', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $building = DB::table('it_asset_locations')->find($buildingId);
        }
        $floor = DB::table('it_asset_locations')->where('type', 'floor')->where('parent_id', $building->id)->whereRaw('LOWER(name) = ?', [strtolower(trim($request->floor_name))])->first();
        if (!$floor) {
            $floorId = DB::table('it_asset_locations')->insertGetId(['name' => trim($request->floor_name), 'type' => 'floor', 'parent_id' => $building->id, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $floor = DB::table('it_asset_locations')->find($floorId);
        }
        $payload = ['name' => trim($request->room_name), 'type' => 'room', 'parent_id' => $floor->id, 'department_id' => $request->department_id ?: null, 'is_active' => true, 'updated_at' => $now];
        if ($request->id) DB::table('it_asset_locations')->where('id', $request->id)->update($payload);
        else { $payload['created_at'] = $now; DB::table('it_asset_locations')->insert($payload); }
        return response()->json(['message' => 'Lokasi berhasil disimpan']);
    }

    public function savePosition(Request $request)
    {
        $this->ready();
        $validator = Validator::make($request->all(), ['id' => 'required|exists:it_support_assets,id', 'x' => 'required|numeric', 'y' => 'required|numeric']);
        if ($validator->fails()) return response()->json(['message' => $validator->errors()->first()], 422);
        DB::table('it_support_assets')->where('id', $request->id)->update(['map_position' => json_encode(['x' => max(0, min(100, $request->x)), 'y' => max(0, min(100, $request->y))]), 'updated_by' => $this->user_id ?? null, 'updated_at' => Carbon::now()]);
        return response()->json(['message' => 'Posisi denah disimpan']);
    }

    public function logIssue(Request $request)
    {
        $this->ready();
        $validator = Validator::make($request->all(), ['asset_id' => 'required|exists:it_support_assets,id', 'status' => 'required|in:issue,maintenance,resolved', 'description' => 'required|string']);
        if ($validator->fails()) return response()->json(['message' => $validator->errors()->first()], 422);
        DB::table('it_asset_maintenance_logs')->insert(['asset_id' => $request->asset_id, 'status' => $request->status, 'description' => $request->description, 'handled_by' => $request->handled_by, 'created_by' => $this->user_id ?? null, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
        DB::table('it_support_assets')->where('id', $request->asset_id)->update(['status' => $request->status === 'resolved' ? 'active' : $request->status, 'updated_by' => $this->user_id ?? null, 'updated_at' => Carbon::now()]);
        return response()->json(['message' => 'Riwayat penanganan berhasil dicatat']);
    }

    public function history(Request $request)
    {
        $this->ready();
        return response()->json(DB::table('it_asset_maintenance_logs')->where('asset_id', $request->asset_id)->latest()->get());
    }
}
