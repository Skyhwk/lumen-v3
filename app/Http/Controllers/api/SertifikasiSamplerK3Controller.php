<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class SertifikasiSamplerK3Controller extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::query()
            ->where('is_active', true)
            ->where('id_jabatan', 94)
            ->where('has_k3_sampler_certification', true)
            ->select([
                'id',
                'nik_karyawan',
                'nama_lengkap',
                'jabatan',
                'status_karyawan',
                'email',
                'department',
                'id_cabang',
                'has_k3_sampler_certification',
            ])
            ->with('cabang:id,nama_cabang');

        return Datatables::of($data)
            ->filterColumn('cabang.nama_cabang', function ($query, $keyword) {
                $query->whereHas('cabang', function ($cabang) use ($keyword) {
                    $cabang->where('nama_cabang', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function getKaryawan(Request $request)
    {
        $data = MasterKaryawan::query()
            ->where('is_active', true)
            ->where('id_jabatan', 94)
            ->where('has_k3_sampler_certification', false)
            ->select([
                'id',
                'nama_lengkap',
            ])
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json(['data' => $data], 200);
    }

    public function store(Request $request)
    {
        $invalid = $this->invalidIdResponse($request);
        if ($invalid) {
            return $invalid;
        }

        $karyawan = MasterKaryawan::find($request->id);
        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
        }
        if ($karyawan->has_k3_sampler_certification) {
            return response()->json(['message' => 'Karyawan sudah memiliki sertifikasi sampler K3'], 400);
        }

        try {
            DB::beginTransaction();
            $karyawan->has_k3_sampler_certification = true;
            $karyawan->save();
            DB::commit();
            return response()->json(['message' => 'Sertifikasi sampler K3 berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function remove(Request $request)
    {
        $invalid = $this->invalidIdResponse($request);
        if ($invalid) {
            return $invalid;
        }

        $karyawan = MasterKaryawan::find($request->id);
        if (!$karyawan) {
            return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
        }
        if (!$karyawan->has_k3_sampler_certification) {
            return response()->json(['message' => 'Karyawan tidak memiliki sertifikasi sampler K3'], 400);
        }

        try {
            DB::beginTransaction();
            $karyawan->has_k3_sampler_certification = false;
            $karyawan->save();
            DB::commit();
            return response()->json(['message' => 'Sertifikasi sampler K3 berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function invalidIdResponse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        return null;
    }
}
