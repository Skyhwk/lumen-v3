<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\KontakDaruratKaryawan;
use App\Models\User;
use App\Models\MedicalCheckup;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;

class DataContactDaruratController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::with(['kontak_darurat'])->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function showById(Request $request) 
    {
        $data = KontakDaruratKaryawan::where('karyawan_id',$request->karyawan_id)->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = KontakDaruratKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'nama_kontak' => $request->nama_kontak,
                'hubungan' => $request->hubungan,
                'nomor_kontak' => $request->nomor_kontak,
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => true
            ]);
            
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = KontakDaruratKaryawan::find($request->id);
            $data->nama_kontak = $request->nama_kontak;
            $data->hubungan = $request->hubungan;
            $data->nomor_kontak = $request->nomor_kontak;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil diupdate', 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = KontakDaruratKaryawan::find($request->id);
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}