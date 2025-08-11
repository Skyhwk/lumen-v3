<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\DataSertifikatKaryawan;
use App\Models\User;
use App\Models\MedicalCheckup;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;

class DataSertifikatController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::with(['sertifikat_karyawan'])->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function showById(Request $request) 
    {
        $data = DataSertifikatKaryawan::where('karyawan_id',$request->karyawan_id)->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = DataSertifikatKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'nama_sertifikat' => $request->nama_sertifikat,
                'tipe_sertifikat' => $request->tipe_sertifikat,
                'nomor_sertifikat' => $request->nomor_sertifikat,
                'deskripsi_sertifikat' => $request->deskripsi_sertifikat,
                'tgl_sertifikat' => $request->tgl_sertifikat,
                'tgl_exp_sertifikat' => $request->tgl_exp_sertifikat,
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

            $data = DataSertifikatKaryawan::find($request->id);
            $data->nama_sertifikat = $request->nama_sertifikat;
            $data->tipe_sertifikat = $request->tipe_sertifikat;
            $data->nomor_sertifikat = $request->nomor_sertifikat;
            $data->deskripsi_sertifikat = $request->deskripsi_sertifikat;
            $data->tgl_sertifikat = $request->tgl_sertifikat;
            $data->tgl_exp_sertifikat = $request->tgl_exp_sertifikat;
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

            $data = DataSertifikatKaryawan::find($request->id);
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