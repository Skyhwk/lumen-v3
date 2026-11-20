<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterAsetKaryawan;
use App\Models\MasterSubKategoriAset;
use App\Services\HrAset\HrGaAssetLinkService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Yajra\Datatables\Datatables;

class MasterAsetKaryawanController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('master_aset_karyawan')) {
            return Datatables::of(collect([]))->make(true);
        }

        $query = MasterAsetKaryawan::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(TRIM(nama)) <> ?', ['id card']);

        if (Schema::hasTable('master_sub_kategori_aset') && Schema::hasColumn('master_aset_karyawan', 'sub_kategori_aset_id')) {
            $query->leftJoin(
                'master_sub_kategori_aset',
                'master_sub_kategori_aset.id',
                '=',
                'master_aset_karyawan.sub_kategori_aset_id'
            )
                ->leftJoin(
                    'master_kategori_aset',
                    'master_kategori_aset.id',
                    '=',
                    'master_sub_kategori_aset.id_kategori'
                )
                ->select([
                    'master_aset_karyawan.id',
                    'master_aset_karyawan.nama',
                    'master_aset_karyawan.sub_kategori_aset_id',
                    'master_aset_karyawan.ga_link_status',
                    'master_aset_karyawan.ga_linked_at',
                    'master_aset_karyawan.created_at',
                    'master_sub_kategori_aset.nama_sub_kategori',
                    'master_kategori_aset.nama_kategori',
                ]);
        } else {
            $query->select([
                'master_aset_karyawan.id',
                'master_aset_karyawan.nama',
                'master_aset_karyawan.created_at',
            ]);
        }

        return Datatables::of($query)
            ->filterColumn('nama', function ($q, $keyword) {
                $q->where('master_aset_karyawan.nama', 'like', "%{$keyword}%");
            })
            ->make(true);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'nama' => 'required|string|max:150',
        ]);

        if (!Schema::hasTable('master_aset_karyawan')) {
            return response()->json([
                'status' => false,
                'message' => 'Tabel master aset karyawan belum tersedia.',
            ], 500);
        }

        $nama = trim((string) $request->nama);
        if (strcasecmp($nama, 'id card') === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Nama ID Card dikelola sistem.',
            ], 422);
        }

        $now = Carbon::now()->toDateTimeString();

        $row = MasterAsetKaryawan::create([
            'nama' => $nama,
            'ga_link_status' => HrGaAssetLinkService::MASTER_STATUS_UNMAPPED,
            'created_by' => $this->karyawan,
            'created_at' => $now,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Master aset karyawan berhasil ditambahkan.',
            'data' => $row,
        ], 201);
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'nama' => 'required|string|max:150',
        ]);

        $row = MasterAsetKaryawan::where('id', (int) $request->id)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        if (strcasecmp(trim((string) $row->nama), 'id card') === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Master ID Card tidak dapat diubah namanya.',
            ], 422);
        }

        $now = Carbon::now()->toDateTimeString();
        $row->nama = trim((string) $request->nama);
        $row->updated_by = $this->karyawan;
        $row->updated_at = $now;
        $row->save();

        return response()->json([
            'status' => true,
            'message' => 'Master aset karyawan berhasil diperbarui.',
            'data' => $row,
        ]);
    }

    public function delete(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
        ]);

        $row = MasterAsetKaryawan::where('id', (int) $request->id)
            ->where('is_active', true)
            ->first();

        if (!$row) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        if (strcasecmp(trim((string) $row->nama), 'id card') === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Master ID Card tidak dapat dinonaktifkan.',
            ], 422);
        }

        $now = Carbon::now()->toDateTimeString();
        $row->is_active = false;
        $row->deleted_by = $this->karyawan;
        $row->deleted_at = $now;
        $row->save();

        return response()->json([
            'status' => true,
            'message' => 'Master aset karyawan berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Mapping master HR → sub kategori GA (master_sub_kategori_aset).
     */
    public function linkSubKategori(Request $request, HrGaAssetLinkService $linkService)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'sub_kategori_aset_id' => 'nullable|integer',
        ]);

        try {
            $subId = $request->filled('sub_kategori_aset_id')
                ? (int) $request->sub_kategori_aset_id
                : null;

            $row = $linkService->linkMasterToSubKategori((int) $request->id, $subId, $this->karyawan);

            return response()->json([
                'status' => true,
                'message' => $subId
                    ? 'Mapping sub kategori GA berhasil.'
                    : 'Mapping sub kategori GA dihapus.',
                'data' => $row,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function getSubKategoriOptions(Request $request)
    {
        if (!Schema::hasTable('master_sub_kategori_aset')) {
            return response()->json([
                'status' => true,
                'data' => [],
            ]);
        }

        $data = MasterSubKategoriAset::with('kategori')
            ->where('is_active', true)
            ->orderBy('nama_sub_kategori')
            ->get()
            ->map(function ($item) {
                $kategori = optional($item->kategori)->nama_kategori;
                $label = trim(($kategori ? $kategori . ' — ' : '') . $item->nama_sub_kategori);

                return [
                    'id' => $item->id,
                    'nama_sub_kategori' => $item->nama_sub_kategori,
                    'nama_kategori' => $kategori,
                    'text' => $label,
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Options loaded successfully',
            'data' => $data,
        ]);
    }
}