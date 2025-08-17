<?php

namespace App\Http\Controllers\api;

use App\Models\MasterWilayahSampling;
use App\Models\MasterCabang;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Datatables;
use Carbon\Carbon;


class MasterWilayahSamplingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $data = MasterWilayahSampling::with('cabang')->where('is_active', 1);

            if ($request->has('id_cabang') && $request->id_cabang) {
                $data->where('id_cabang', $request->id_cabang);
            }

            if ($request->has('search') && $request->search['value']) {
                $searchValue = $request->search['value'];
                $data->where(function ($query) use ($searchValue) {
                    $query->where('wilayah', 'like', "%{$searchValue}%")
                        ->orWhere('status_wilayah', 'like', "%{$searchValue}%");
                });
            }

            return Datatables::of($data)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal memuat data',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_cabang' => 'required|exists:master_cabangs,id',
            'wilayah' => 'required|string|max:255',
            'status_wilayah' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $data = MasterWilayahSampling::updateOrCreate(
                ['id' => $request->id],
                [
                    'id_cabang' => $request->id_cabang,
                    'wilayah' => $request->wilayah,
                    'status_wilayah' => $request->status_wilayah,
                    'created_by' => $this->karyawan,
                    'updated_by' => $this->karyawan,
                ]
            );

            DB::commit();
            $message = $request->id ? 'Data Berhasil Diperbarui' : 'Data Berhasil Disimpan';
            return response()->json(['data' => $data, 'message' => $message, 'success' => true], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCabang()
    {
        $data = MasterCabang::where('is_active', true)->get();

        return response()->json(['data' => $data, 'status' => 200, 'success' => true], 200);
    }

    public function destroy(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = MasterWilayahSampling::where('id', $request->id)->first();
            $data->is_active = false;
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data Berhasil Dihapus', 'status' => 200, 'success' => true], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
