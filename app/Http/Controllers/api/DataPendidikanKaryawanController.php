<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\PendidikanKaryawan;
use App\Models\User;
use App\Models\MedicalCheckup;
use Carbon\Carbon;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;

class DataPendidikanKaryawanController extends Controller
{
   public function index(Request $request)
    {
        $data = MasterKaryawan::with(['pendidikan_karyawan'])->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function showById(Request $request) 
    {
        $data = PendidikanKaryawan::where('karyawan_id',$request->karyawan_id)->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = PendidikanKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'institusi' => $request->institusi,
                'jenjang' => $request->jenjang,
                'jurusan' => $request->jurusan,
                'tahun_masuk' => $request->tahun_masuk,
                'tahun_lulus' => $request->tahun_lulus,
                'kota' => $request->kota,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
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

            $data = PendidikanKaryawan::find($request->id);
            $data->institusi = $request->institusi;
            $data->jenjang = $request->jenjang;
            $data->jurusan = $request->jurusan;
            $data->tahun_masuk = $request->tahun_masuk;
            $data->tahun_lulus = $request->tahun_lulus;
            $data->kota = $request->kota;
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

     public function destroy(Request $request){
        try {
            DB::beginTransaction();

            $data = PendidikanKaryawan::find($request->id);
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