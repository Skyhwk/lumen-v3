<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterCabang;
use App\Models\MasterWilayahK3;
use App\Models\MasterWilayahSampling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class MasterWilayahK3Controller extends Controller
{
    public function index(Request $request)
    {
        $data = MasterWilayahK3::with(['cabang', 'wilayahSampling'])
            ->where('is_active', true);

        return Datatables::of($data)
            ->addColumn('lingkup_wilayah', function ($row) {
                $items = $row->wilayahSampling
                    ->pluck('wilayah')
                    ->filter()
                    ->values();

                return $items->isEmpty() ? '-' : $items->implode(', ');
            })
            ->addColumn('nama_cabang', function ($row) {
                return optional($row->cabang)->nama_cabang ?? '-';
            })
            ->addColumn('id_master_wilayah_sampling', function ($row) {
                return $row->wilayahSampling->pluck('id')->values()->all();
            })
            ->make(true);
    }

    public function store(Request $request)
    {
        $wilayahPelanggan = trim((string) $request->wilayah_pelanggan);
        if ($wilayahPelanggan === '') {
            return response()->json([
                'success' => false,
                'message' => 'Wilayah pelanggan wajib diisi.',
            ], 422);
        }

        $duplicateQuery = MasterWilayahK3::where('is_active', true)
            ->where('wilayah_pelanggan', $wilayahPelanggan);

        if ($request->id) {
            $duplicateQuery->where('id', '!=', $request->id);
        }

        if ($duplicateQuery->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Wilayah pelanggan sudah terdaftar.',
            ], 422);
        }

        $samplingIds = collect($request->id_master_wilayah_sampling ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($request->id_cabang && !empty($samplingIds)) {
            $validCount = MasterWilayahSampling::where('id_cabang', $request->id_cabang)
                ->where('is_active', true)
                ->whereIn('id', $samplingIds)
                ->count();

            if ($validCount !== count($samplingIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lingkup wilayah tidak valid untuk cabang yang dipilih.',
                ], 422);
            }
        }

        $timestamp = date('Y-m-d H:i:s');

        if ($request->id && !MasterWilayahK3::find($request->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($request, $wilayahPelanggan, $samplingIds, $timestamp) {
            if ($request->id) {
                $record = MasterWilayahK3::find($request->id);

                $record->wilayah_pelanggan = $wilayahPelanggan;
                $record->id_cabang = $request->id_cabang ?: null;
                $record->updated_by = $this->karyawan;
                $record->updated_at = $timestamp;
                $record->save();

                $record->wilayahSampling()->sync($samplingIds);
            } else {
                $record = MasterWilayahK3::create([
                    'wilayah_pelanggan' => $wilayahPelanggan,
                    'id_cabang' => $request->id_cabang ?: null,
                    'is_active' => true,
                    'created_by' => $this->karyawan,
                    'created_at' => $timestamp,
                ]);

                $record->wilayahSampling()->sync($samplingIds);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil disimpan.',
        ], 200);
    }

    public function destroy(Request $request)
    {
        $record = MasterWilayahK3::find($request->id);
        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        $timestamp = date('Y-m-d H:i:s');
        $record->is_active = false;
        $record->deleted_by = $this->karyawan;
        $record->deleted_at = $timestamp;
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihapus.',
        ], 200);
    }

    public function getCabang()
    {
        $data = MasterCabang::where('is_active', true)->orderBy('nama_cabang')->get();

        return response()->json([
            'data' => $data,
            'success' => true,
        ], 200);
    }

    public function getWilayahSamplingByCabang(Request $request)
    {
        $data = MasterWilayahSampling::where('id_cabang', $request->id_cabang)
            ->where('is_active', true)
            ->orderBy('wilayah')
            ->get(['id', 'wilayah', 'status_wilayah']);

        return response()->json([
            'data' => $data,
            'success' => true,
        ], 200);
    }
}
